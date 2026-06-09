# Hướng dẫn cấu hình Queue Redis và Job Batching với MongoDB (Cho Laravel Horizon)

Tài liệu này hướng dẫn chi tiết cách thiết lập dự án Laravel sử dụng **Redis** làm Queue Driver chính cho Horizon, đồng thời lưu trữ các dữ liệu **Job Batching** và **Failed Jobs** trong **MongoDB** để tối ưu hóa hiệu suất hệ thống.

---

## 1. Cài đặt các thư viện cần thiết

Cài đặt thư viện MongoDB tương thích tốt với Laravel 12 và PHP 8.4:
```bash
composer require mongodb/laravel-mongodb:5.7.0
```
> **Lưu ý quan trọng**: Không nên nâng cấp lên bản `5.8.0` nếu môi trường của bạn đang chạy PHP thấp hơn 8.6, vì bản `5.8.0` bị lỗi biên dịch `Class "SortDirection" not found` (do MongoDB gọi trực tiếp tính năng PHP 8.6). Bản `5.7.0` chạy ổn định và mượt mà nhất.

---

## 2. Cấu hình các biến môi trường (`.env`)

Mở file `.env` và cấu hình các giá trị sau:

```env
# Kết nối CSDL chính sử dụng MongoDB
DB_CONNECTION=mongodb
MONGO_DB_HOST=mongo
MONGO_DB_PORT=27017
MONGO_DB_DATABASE=laravel
MONGO_DB_USERNAME=admin
MONGO_DB_PASSWORD=your_password
MONGO_DB_AUTHENTICATION_DATABASE=admin
MONGO_DB_REPLICA_SET=rs0

# Cấu hình Cache & Queue sử dụng Redis để Horizon theo dõi
QUEUE_CONNECTION=redis
CACHE_STORE=redis
SESSION_DRIVER=redis

# Cấu hình thông tin Redis
REDIS_CLIENT=predis
REDIS_HOST=redis
REDIS_PORT=6379
REDIS_PASSWORD=null
```

---

## 3. Cấu hình Queue & Batching (`config/queue.php`)

Mở file `config/queue.php` và cập nhật cấu hình **`batching`** cùng **`failed`** như sau để hướng dữ liệu batch và lỗi về MongoDB:

```php
    /*
    |--------------------------------------------------------------------------
    | Job Batching
    |--------------------------------------------------------------------------
    |
    | The following options configure the database and table that should be used
    | to store information about job batches for monitoring and control.
    |
    */

    'batching' => [
        'database' => env('QUEUE_DB_CONNECTION', 'mongodb'),
        'table' => 'job_batches',
    ],

    /*
    |--------------------------------------------------------------------------
    | Failed Queue Jobs
    |--------------------------------------------------------------------------
    |
    | These options configure the behavior of failed queue jobs so you can control
    | which database and table are used to store the jobs that fail.
    |
    */

    'failed' => [
        'driver' => env('QUEUE_FAILED_DRIVER', 'database-uuids'),
        'database' => env('QUEUE_DB_CONNECTION', 'mongodb'),
        'table' => 'failed_jobs',
    ],
```

---

## 4. Tạo Custom Repository giải quyết lỗi giao diện Horizon bị trống (Blank UI)

Mặc định, MongoDB driver trả về kết quả dạng mảng (Array) thô thay vì đối tượng `Batch` tiêu chuẩn của Laravel. Điều này làm cho giao diện Web của Laravel Horizon hiển thị trống trơn ở tab **Batches**.

### Bước 4.1: Tạo file `app/Bus/CustomMongoBatchRepository.php`

Tạo file mới tại đường dẫn trên và định nghĩa logic mapping đối tượng `Batch`:

```php
<?php

namespace App\Bus;

use MongoDB\Laravel\Bus\MongoBatchRepository;
use MongoDB\BSON\ObjectId;

class CustomMongoBatchRepository extends MongoBatchRepository
{
    /**
     * Lấy danh sách các batches và chuyển đổi sang Object Batch chuẩn của Laravel.
     *
     * @param  int  $limit
     * @param  mixed  $before
     * @return \Illuminate\Bus\Batch[]
     */
    public function get($limit = 50, $before = null): array
    {
        if (is_string($before)) {
            $before = new ObjectId($before);
        }

        $collection = $this->getConnection()->getCollection($this->table);

        $records = $collection->find(
            $before ? ['_id' => ['$lt' => $before]] : [],
            [
                'limit' => $limit,
                'sort' => ['_id' => -1],
                'typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array'],
            ],
        )->toArray();

        return array_map(function ($record) {
            return $this->toBatch($record);
        }, $records);
    }
}
```

### Bước 4.2: Đăng ký Repository trong `app/Providers/AppServiceProvider.php`

Mở file `AppServiceProvider.php` và thêm phương thức `register()` để ghi đè Repository mặc định của MongoDB:

```php
    public function register(): void
    {
        // Ghi đè MongoBatchRepository của package bằng class Custom của chúng ta
        $this->app->extend(\MongoDB\Laravel\Bus\MongoBatchRepository::class, function ($repository, $app) {
            $connection = $app->make('db')->connection($app->config->get('queue.batching.database'));

            return new \App\Bus\CustomMongoBatchRepository(
                $app->make(\Illuminate\Bus\BatchFactory::class),
                $connection,
                $app->config->get('queue.batching.collection', 'job_batches'),
            );
        });
    }
```

---

## 5. Khởi động lại hệ thống

Mỗi lần bạn sửa đổi code PHP liên quan đến cấu hình Queue hoặc Service Provider, bạn cần phải khởi động lại daemon của Laravel Horizon để nhận mã nguồn mới:

```bash
# Nếu chạy qua docker-compose
docker compose restart horizon

# Hoặc bằng lệnh terminal
php artisan horizon:terminate
```

Bây giờ hệ thống của bạn đã chạy mượt mà: Horizon hoạt động trên Redis với tốc độ cực nhanh, trong khi toàn bộ dữ liệu Job Batches và Failed Jobs được lưu trữ gọn gàng và dễ dàng quản lý tại MongoDB!
