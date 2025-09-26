<?php

namespace Naresh\UserDiscounts\Traits;

trait ResolvesUserModel
{
    protected function resolveUserModel(): string
    {
        $configured = config('user-discounts.models.user');

        if ($configured) {
            return $configured;
        }

        $provider = config('auth.defaults.provider');

        if ($provider && ($model = config("auth.providers.{$provider}.model"))) {
            return $model;
        }

        return config('auth.providers.users.model', '\\App\\Models\\User');
    }
}