<?php

namespace App\Bus;

use MongoDB\Laravel\Bus\MongoBatchRepository;
use MongoDB\BSON\ObjectId;

class CustomMongoBatchRepository extends MongoBatchRepository
{
    /**
     * Retrieve a list of batches.
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
