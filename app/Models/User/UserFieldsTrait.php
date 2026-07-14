<?php

namespace App\Models\User;

use Helper\Field\Field;
use Helper\Type\Gender\Gender;
use Helper\Type\Status\Status;

trait UserFieldsTrait {
    public function indexFields(): array {
        return [
            Field::id(),
            Field::phone(),
            Field::email(),
            Field::state(),
            Field::gender(),
            Field::fullName('full_name_localized'),
            Field::displayName('full_name__localized'),
            Field::makeFile('photo')->disk(DEFAULT_STORAGE),
            Field::detail(fn ($data) => $data?->userDetail?->resource()),
            Field::make('is_logged_in', fn ($data) => $data->isLoggedIn()),
            Field::roles(fn ($data) => $data?->roles?->collection('idAndNameFields')),
            Field::createdAt(fn ($data) => $data->created_at->format(DATE_FORMAT)),
            Field::status(fn ($data) => Status::getFullTypeUsingId($data->status)),
            Field::genderData(fn ($data) => Gender::getFullTypeUsingId($data->gender)),
            Field::permissionOverridesCount()->asInt(),
        ];
    }

    public function authUserFields(): array {
        return [
            Field::id(),
            Field::email(),
            Field::bio('userDetail.bio__localized'),
            Field::makeFile('photo')->disk(DEFAULT_STORAGE),
            Field::makeFile('cover_photo')->disk(DEFAULT_STORAGE),

            Field::lastName('userDetail.last_name__localized'),
            Field::firstName('userDetail.first_name__localized'),
            Field::middleName('userDetail.middle_name__localized'),
            Field::dob(fn ($data) => $data->userDetail?->birth_date?->format(DATE_FORMAT)),

            Field::roles(fn ($data) => $data?->roles?->collection('idAndNameFields')),
            Field::make('joined_date', fn ($data) => $data->created_at->format(DATE_FORMAT)),
            Field::makeType('gender', 'userDetail.gender', typeClass: Gender::class, typeFunction: 'getFullTypeUsingId'),
        ];
    }

    public function idAndNameFields(): array {
        return [
            Field::id(),
            Field::email(),
            Field::fullName('full_name_localized'),
            Field::lastName('userDetail.last_name_localized'),
            Field::firstName('userDetail.first_name_localized'),
            Field::middleName('userDetail.middle_name_localized'),
        ];
    }

    public static function userDropDown(): array {
        return [
            Field::id(),
            Field::email(),
            Field::name('full_name_localized'),
            Field::displayName('full_name__localized'),
        ];
    }
}
