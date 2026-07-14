<?php

namespace App\Models\User;

use Helper\Field\Field;
use Helper\Model\UserModel;
use Helper\Type\Gender\Gender;

class UserDetail extends UserModel {

    /**
     * Get the attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'first_name' => 'array',
        'middle_name' => 'array',
        'last_name' => 'array',
        'bio' => 'array',
        'birth_date' => 'datetime',
    ];

    /**
     * Fillable attributes
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'photo', 'gender', 'phone', 'email',
        'first_name', 'middle_name', 'last_name',
        'birth_date', 'national_id', 'bio',
    ];

    public function indexFields(): array {
        return [
            Field::id(),
            Field::phone(),
            Field::email(),
            Field::gender(),
            Field::birthDate(fn ($data) => $data->birth_date->format(DATE_FORMAT)),
            Field::nationalId(),
            Field::makeLocalized('bio'),
            Field::makeLocalized('first_name'),
            Field::makeLocalized('middle_name'),
            Field::makeLocalized('last_name'),
            Field::makeFile('photo')->disk(DEFAULT_STORAGE),
            Field::gender_(fn ($data) => Gender::typeNames($data->gender)),
        ];
    }
}
