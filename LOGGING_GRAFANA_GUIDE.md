# Hệ Thống Logging với Grafana - Laravel

## 🏗️ Kiến trúc

```
┌─────────────────┐     ┌───────────────┐     ┌──────────────┐     ┌──────────────┐
│  Laravel App    │────▶│  Log Files    │────▶│  Grafana     │────▶│    Loki      │
│  (storage/logs) │     │  (*.log)      │     │  Alloy       │     │  (storage)   │
└─────────────────┘     └───────────────┘     │  (collector) │     └──────┬───────┘
                                              └──────────────┘            │
┌─────────────────┐                                  ▲                    │
│  Docker         │──── container logs ──────────────┘                    │
│  Containers     │    (docker socket)                                    ▼
└─────────────────┘                                                ┌──────────────┐
                                                                   │   Grafana    │
                                                                   │  (dashboard) │
                                                                   │  :3000       │
                                                                   └──────────────┘
```

## 📦 Stack Components

| Service     | Image                      | Port  | Mô tả                                         |
|-------------|----------------------------|-------|------------------------------------------------|
| **Loki**    | grafana/loki:3.5.0         | 3100  | Log aggregation engine - lưu trữ logs          |
| **Alloy**   | grafana/alloy:v1.17.0      | 12345 | Agent thu thập logs (thay thế Promtail - EOL)  |
| **Grafana** | grafana/grafana:11.6.0     | 3000  | Dashboard UI - trực quan hóa logs              |

> **Note:** Promtail đã ngừng hỗ trợ (EOL) từ tháng 3/2026. Grafana Alloy là công cụ thay thế chính thức với hỗ trợ OpenTelemetry, logs, metrics, traces trong cùng một binary.

## 🚀 Khởi động

### Bước 1: Start toàn bộ stack
```bash
docker compose up -d
```

### Bước 2: Kiểm tra services
```bash
# Kiểm tra tất cả containers đang chạy
docker compose ps

# Kiểm tra Loki ready
curl -s http://localhost:3100/ready

# Kiểm tra Alloy UI
# Mở browser: http://localhost:12345
```

### Bước 3: Truy cập Grafana
- **URL**: http://localhost:3000
- **Username**: Thiết lập qua `GRAFANA_ADMIN_USER` trong file `.env` (mặc định: `admin`)
- **Password**: Thiết lập qua `GRAFANA_ADMIN_PASSWORD` trong file `.env` (mặc định: `admin`)
- Dashboard **"Laravel Application Logs"** được tự động load

## 📊 Dashboard Features

Dashboard đã được pre-built với các panels:

### Row 1 - Overview Stats
| Panel | Mô tả |
|-------|--------|
| 📝 Total Log Entries | Tổng số log entries trong khoảng thời gian |
| 🔴 Error Count | Số lượng errors/critical/emergency |
| 🟡 Warning Count | Số lượng warnings |
| 🔵 Info Count | Số lượng info logs |

### Row 2 - Log Volume Chart
- **Stacked bar chart** hiển thị log volume theo level (error, warning, info, debug)
- Màu sắc theo level: 🔴 Error, 🟠 Warning, 🔵 Info, 🟣 Debug

### Row 3 - Error Stream
- **Live log stream** chỉ hiển thị errors, exceptions, critical, emergency

### Row 4 - All Logs (Filtered)
- **Full log stream** với search variable `$search`

### Row 5 - Docker Container Logs
- Logs từ tất cả Docker containers (collapsible)

## 🔍 Truy vấn LogQL

```logql
# Xem tất cả Laravel logs
{job="laravel"}

# Filter theo level
{job="laravel"} |~ `(?i)\.ERROR:`
{job="laravel"} |~ `(?i)\.WARNING:`

# Tìm kiếm theo keyword
{job="laravel"} |= "QueryException"

# Đếm errors theo thời gian
sum(count_over_time({job="laravel"} |~ `(?i)error` [5m]))

# Docker container logs
{job="docker"}

# Docker logs theo container name
{job="docker", container="laravel_app"}
```

## 📁 Cấu trúc files

```
docker/
├── alloy/
│   └── config.alloy              # Alloy pipeline config (River syntax)
├── grafana/
│   └── provisioning/
│       ├── datasources/
│       │   └── datasource.yml    # Kết nối Grafana → Loki
│       └── dashboards/
│           ├── dashboard.yml     # Dashboard provisioning config
│           └── laravel-logging.json  # Pre-built dashboard
└── loki/
    └── loki-config.yml           # Loki server config
```

## ⚙️ Cấu hình chi tiết

### Alloy (`docker/alloy/config.alloy`)
Pipeline component-based:
- **`loki.write`** → Gửi logs đến Loki endpoint
- **`local.file_match`** → Discover Laravel log files (`storage/logs/*.log`)
- **`loki.source.file`** → Tail & đọc file logs
- **`loki.process`** → Multiline parsing + regex extraction (timestamp, channel, level)
- **`discovery.docker`** → Auto-discover Docker containers
- **`loki.source.docker`** → Thu thập container logs qua Docker socket

### Loki (`docker/loki/loki-config.yml`)
- **Storage**: Filesystem-based (TSDB)
- **Retention**: 30 ngày (`720h`)
- **Compaction**: Tự động mỗi 10 phút
- **Cache**: Embedded cache 100MB

### Laravel Logging (`.env`)
- `LOG_CHANNEL=stack`
- `LOG_STACK=daily` (log rotation tự động)
- `LOG_LEVEL=debug`

## 🛠️ Troubleshooting

### Alloy không thu thập logs
```bash
# Mở Alloy UI để xem pipeline graph
# http://localhost:12345

# Xem Alloy logs
docker compose logs alloy -f

# Kiểm tra config syntax
docker compose exec alloy alloy fmt /etc/alloy/config.alloy
```

### Loki không nhận logs
```bash
# Kiểm tra Loki health
curl http://localhost:3100/ready

# Xem Loki logs
docker compose logs loki -f
```

### Grafana không hiển thị data
1. Vào **Connections → Data Sources → Loki** → Test connection
2. Kiểm tra URL: `http://loki:3100`
3. Thử query trực tiếp: **Explore → Loki → `{job="laravel"}`**

### Reset toàn bộ logging stack
```bash
docker compose down -v --remove-orphans
docker compose up -d
```

## 🔒 Production Notes

1. **Grafana password**: Đổi biến `GRAFANA_ADMIN_PASSWORD` trong file `.env`
2. **Loki storage**: Chuyển sang S3/GCS thay vì filesystem
3. **Retention policy**: Điều chỉnh `retention_period` trong Loki config
4. **Network**: Không expose port 3100 (Loki) và 12345 (Alloy) ra public

## 📝 Test Logging

```bash
docker compose exec app php artisan tinker

# Trong tinker:
Log::info('Test info message', ['user' => 'admin']);
Log::warning('Test warning', ['action' => 'login']);
Log::error('Test error', ['exception' => 'TestException']);
Log::critical('Test critical error!');
```

Sau đó vào Grafana (http://localhost:3000) để xem logs xuất hiện trên dashboard.
