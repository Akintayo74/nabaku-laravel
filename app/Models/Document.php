<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Laravel\Eloquent\Model;

abstract class Document extends Model
{
    protected $connection = 'mongodb';

    protected $guarded = ['_id', 'id'];

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updatedAt';

    protected $hidden = ['password', 'activationToken', 'activationExpires', 'resetPasswordToken', 'resetPasswordExpires', 'deletedAt', 'reservationApplied', 'checkoutPayload'];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if ($model->getAttribute('__v') === null) {
                $model->setAttribute('__v', 0);
            }
        });
    }

    protected function serializeDate(\DateTimeInterface $date): string
    {
        return CarbonImmutable::instance($date)->utc()->format('Y-m-d\TH:i:s.v\Z');
    }

    public function setAttribute($key, $value)
    {
        if (in_array($key, ['organization', 'project', 'bank', 'address', 'cac', 'contact'], true) && is_string($value) && preg_match('/^[a-f0-9]{24}$/i', $value)) {
            $value = new ObjectId($value);
        }

        return parent::setAttribute($key, $value);
    }

    public function toArray(): array
    {
        $data = parent::toArray();
        $data['_id'] = (string) $this->getKey();
        if (! in_array(static::class, [Project::class, Activity::class, Transaction::class], true)) {
            $data['id'] = $data['_id'];
        } else {
            unset($data['id'], $data['__v']);
        }
        foreach (['organization', 'project', 'bank', 'address', 'cac', 'contact'] as $key) {
            if (($data[$key] ?? null) instanceof ObjectId) {
                $data[$key] = (string) $data[$key];
            }
        }

        return $this->jsonValue($data);
    }

    private function jsonValue(mixed $value): mixed
    {
        if ($value instanceof ObjectId) {
            return (string) $value;
        }
        if ($value instanceof UTCDateTime) {
            return $this->serializeDate($value->toDateTime());
        }
        if ($value instanceof \DateTimeInterface) {
            return $this->serializeDate($value);
        }
        if ($value instanceof \stdClass) {
            $value = get_object_vars($value);
        }
        if ($value instanceof \Traversable) {
            $value = iterator_to_array($value);
        }
        if (is_array($value)) {
            return array_map($this->jsonValue(...), $value);
        }

        return $value;
    }
}
