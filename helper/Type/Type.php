<?php

namespace Helper\Type;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;
use Translation\BackLang;

class Type {

    /**
     * The back language translation
     * this might differ based on the module
     *
     * @var mixed
     */
    public const BACK_LANG = BackLang::class;

    /**
     * The id of the class
     *
     * @var int|string $id
     */
    public static $id;

    /**
     * The translation name for
     * the class
     *
     * @var string $name
     */
    public static $name;

    /**
     * The translation color for
     * the class
     *
     * @var string $color
     */
    public static $color;

    /**
     * The translation icon for
     * the class
     *
     * @var string $icon
     */
    public static $icon;

    /**
     * The translation description for
     * the class
     *
     * @var string $description
     */
    public static $description;

    /**
     * The extension for
     * the class
     *
     * @var string $extension
     */
    public static $extension;

    /**
     * The classes grouped in
     * that type
     *
     * for examples for Gender
     * we have [Male::class, Female::class]
     *
     * @var array<string> TYPES
     */
    public const TYPES = [];

    /**
     * Returns the id of the class
     *
     * @return int|string
     */
    public static function id(): int|string {
        return static::$id;
    }

    /**
     * Returns the translation name of the
     * the class
     *
     * @return string
     */
    public static function name(): string {
        return static::$name;
    }

    /**
     * Returns the color of the class
     *
     * @return string
     */
    public static function color(): string {
        return static::$color;
    }

    /**
     * Returns the icon of the class
     *
     * @return string
     */
    public static function icon(): string {
        return static::$icon;
    }

    /**
     * Returns the description of the class
     *
     * @return string
     */
    public static function description(): string {
        return static::$description;
    }

    /**
     * Returns the types for that class
     *
     * @param mixed|null $typeId
     * @return array<int, string>|string|null
     */
    public static function getTypes($typeId = null): array|string|null {
        if ($typeId == null) {
            return static::TYPES;
        }

        $typeClasses = [];
        foreach (static::TYPES as $typeClass) {
            if (!is_array($typeId)) {
                if ($typeClass::id() == $typeId) {
                    return $typeClass;
                }

                continue;
            }

            if (in_array($typeClass::id(), $typeId)) {
                $typeClasses[$typeClass::id()] = $typeClass;
            }
        }

        return count($typeClasses) > 0
            ? $typeClasses
            : null;
    }

    /**
     * Returns the ids of the classes
     * that are included in the TYPES variable
     *
     * @return array<int, int|string>
     */
    public static function typeIds(): array {
        $ids = [];
        foreach (static::TYPES as $typeClass) {
            array_push($ids, $typeClass::id());
        }

        return $ids;
    }

    /**
     * Returns an associative array for the types
     * using id() ans the key and the translation
     * as a value
     *
     * for example
     * [1 => 'Male']
     *
     * @param mixed|null $typeId
     *
     * @return array<int|string, string>|string
     */
    public static function typeNames($typeId = null): array|string {
        $typeNames = [];
        foreach (static::TYPES as $typeClass) {
            if ($typeId !== null) {
                if (!is_array($typeId)) {
                    if ($typeClass::id() == $typeId) {
                        return static::BACK_LANG::get($typeClass::name());
                    }

                    continue;
                }

                if (in_array($typeClass::id(), $typeId)) {
                    $typeNames[$typeClass::id()] = static::BACK_LANG::get($typeClass::name());
                }

                continue;
            }

            $typeNames[$typeClass::id()] = static::BACK_LANG::get($typeClass::name());
        }

        return $typeNames;
    }

    /**
     * Returns an associative array for the types
     * the key and the translation
     * as a value
     *
     * for example
     * ['id' => 1, 'name' => 'started', 'color'=>'#3F7DD8']
     *
     * @return array<int|string, string>
     */
    public static function getIdAndNameWithColors(): array {
        $typeColors = [];
        foreach (static::TYPES as $typeClass) {
            array_push(
                $typeColors,
                [
                    'id' => $typeClass::id(),
                    'name' => static::BACK_LANG::get($typeClass::name()),
                    'color' => $typeClass::color(),
                ]
            );
        }

        return $typeColors;
    }
    /**
     * Returns an associative array for the types
     * the key and the translation
     * as a value
     *
     * for example
     * ['id' => 1, 'name' => 'started', 'color'=>'#3F7DD8']
     *
     * @return array<int|string, string>
     */
    public static function getIdAndNameWithColorsAndIcons(): array {
        $typeColors = [];
        foreach (static::TYPES as $typeClass) {
            array_push(
                $typeColors,
                [
                    'id' => $typeClass::id(),
                    'name' => static::BACK_LANG::get($typeClass::name()),
                    'color' => $typeClass::color(),
                    'icon' => $typeClass::icon(),
                ]
            );
        }

        return $typeColors;
    }
    /**
     * Returns an associative array for the types
     * using id as the key and the translation
     * and color as a value
     */

    public static function getIdNameColorUsingId($id) {
        foreach (static::TYPES as $typeClass) {
            if ($typeClass::id() == $id) {
                return [
                    'id' => $typeClass::id(),
                    'name' => static::BACK_LANG::get($typeClass::name()),
                    'color' => $typeClass::color(),
                ];
            }
        }
        return null;
    }


    /**
     * Returns an associative array with id, name, color, and icon for a given id
     *
     * @param mixed $id
     * @return array|null
     */
    public static function getIdNameColorIconUsingId($id) {
        foreach (static::TYPES as $typeClass) {
            if ($typeClass::id() == $id) {
                return [
                    'id' => $typeClass::id(),
                    'name' => static::BACK_LANG::get($typeClass::name()),
                    'color' => $typeClass::color(),
                    'icon' => $typeClass::icon(),
                ];
            }
        }
        return null;
    }

    /**
     * Returns an associative array for the types
     * using ids as the key and the translation
     * and color as a value and icons
     */
    public static function getIdNameColorIconDescriptionUsingId($id) {
        foreach (static::TYPES as $typeClass) {
            if ($typeClass::id() == $id) {
                return [
                    'id' => $typeClass::id(),
                    'name' => static::BACK_LANG::get($typeClass::name()),
                    'color' => $typeClass::color(),
                    'icon' => $typeClass::icon(),
                    'description' => static::BACK_LANG::get($typeClass::description()),
                ];
            }
        }
        return null;
    }

    /**
     * Returns an array of arrays for the types
     * using id and the translation as a value
     *
     * for example
     * [
     *  ['id' => 1, 'name' => 'Male']
     * ]
     *
     * @return array<int, array>
     */
    public static function idAndName(): array {
        $idAndNames = [];

        foreach (static::TYPES as $typeClass) {
            array_push($idAndNames, [
                'id' => $typeClass::id(),
                'name' => static::BACK_LANG::get($typeClass::name()),
            ]);
        }

        return $idAndNames;
    }

    /**
     * Returns an array of arrays for the types
     * with all their variables
     *
     * for example
     * [
     *  ['id' => 1, 'name' => 'Male', 'color' => '#1234', 'isRequired' => 'true']
     * ]
     *
     * @return array<int, array>
     */
    public static function getFullType(): array {
        $types = [];

        foreach (static::TYPES as $typeClass) {
            $vars = get_class_vars($typeClass);
            $typeData = [];

            foreach ($vars as $key => $value) {
                if ($key === 'name') {
                    $typeData[$key] = static::BACK_LANG::get($typeClass::name());
                } else {
                    $typeData[$key] = $value;
                }
            }

            $types[] = $typeData;
        }

        return $types;
    }

    /**
     * Returns an associative array for the types
     * using translation key as the key and the id()
     * as a value
     *
     * for example
     * ['male' => 1]
     *
     * @return array<string, int|string>
     */
    public static function nameIdPair(): array {
        $keyIdPair = [];

        foreach (static::TYPES as $typeClass) {
            $keyIdPair[$typeClass::name()] = $typeClass::id();
        }

        return $keyIdPair;
    }

    /**
     * Returns a laravel validation rule
     * like in:1,2 using Rule::in() definition
     *
     * @return Illuminate\Validation\Rules\In
     */
    public static function ruleIn(): In {
        return Rule::in(static::typeIds());
    }

    /**
     * Find a specific type based on
     * their id
     *
     * @param mixed $id
     * @return bool|static
     */
    public static function find($id) {
        foreach (static::TYPES as $typeClass) {
            if ($typeClass::id() == $id) {
                return $typeClass;
            }
        }

        return false;
    }

    /**
     * Get Type name based on
     * their id
     *
     * @param mixed $id
     * @return bool|static
     */
    public static function nameById($id) {
        $typeClass = static::find($id);
        return $typeClass ? static::BACK_LANG::get($typeClass::name()) : null;
    }

    /**
     * Check if the given id exists in the TYPES array.
     *
     * @param mixed $id
     * @return bool
     */
    public static function exists($id): bool {
        foreach (static::TYPES as $typeClass) {
            if ($typeClass::id() == $id) {
                return true;
            }
        }
        return false;
    }

    public static function existsInArray($ids): bool {
        $inputIds = array_map(function ($item) {
            return is_array($item) && isset($item['id']) ? $item['id'] : $item;
        }, $ids);

        $typeIds = array_map(fn ($typeClass) => $typeClass::id(), static::TYPES);

        foreach ($inputIds as $id) {
            if (in_array($id, $typeIds)) {
                return true;
            }
        }

        return false;
    }

    public static function getIdAndNameUsingIds($ids) {
        $result = [];
        foreach ($ids as $id) {
            $typeClass = static::find($id);
            if ($typeClass) {
                $result[] = ['id' => $typeClass::id(), 'name' => static::BACK_LANG::get($typeClass::name())];
            }
        }
        return $result;
    }

    public static function getFullTypeUsingId($id) {

        $types = null;
        foreach (static::TYPES as $typeClass) {
            if ($typeClass::id() == $id) {
                $vars = get_class_vars($typeClass);
                $typeData = [];

                foreach ($vars as $key => $value) {
                    if ($key === 'name') {
                        $typeData[$key] = static::BACK_LANG::get($typeClass::name());
                    } else {
                        $typeData[$key] = $value;
                    }
                }

                $types = $typeData;

                break;
            }
        }

        return $types;
    }

    public static function getIdAndNameUsingId($id) {

        foreach (static::TYPES as $type) {
            if ($type::id() == $id) {
                return [
                    'id' => $type::id(),
                    'name' => static::BACK_LANG::get($type::name()),
                ];
            }
        }

        return null;
    }

    public static function getIdNameAndDescriptionUsingId($id) {
        foreach (static::TYPES as $type) {
            if ($type::id() == $id) {
                return [
                    'id' => $type::id(),
                    'name' => static::BACK_LANG::get($type::name()),
                    'description' => static::BACK_LANG::get($type::description()),
                ];
            }
        }
        return null;
    }

    public static function getIdNameColorAndDescriptionUsingId($id) {
        foreach (static::TYPES as $type) {
            if ($type::id() == $id) {
                return [
                    'id' => $type::id(),
                    'name' => static::BACK_LANG::get($type::name()),
                    'color' => $type::$color,
                    'description' => static::BACK_LANG::get($type::description()),
                ];
            }
        }
        return null;
    }

    public static function getIdNameAndExtensionUsingId($id) {
        foreach (static::TYPES as $typeClass) {
            if ($typeClass::id() == $id) {
                return [
                    'id' => $typeClass::id(),
                    'name' => static::BACK_LANG::get($typeClass::name()),
                    'extension' => $typeClass::$extension,
                ];
            }
        }
        return null;
    }

    public static function getIdNameAndExtensionsUsingIds($ids) {
        $result = [];
        foreach ($ids as $id) {
            $typeClass = static::find($id);
            if ($typeClass) {
                $result[] = [
                    'id' => $typeClass::id(),
                    'name' => static::BACK_LANG::get($typeClass::name()),
                    'extension' => $typeClass::$extension,
                ];
            }
        }
        return $result;
    }
    public static function getIdNameAndDescriptionsUsingIds($ids) {
        $result = [];
        foreach ($ids as $id) {
            $typeClass = static::find($id);
            if ($typeClass) {
                $result[] = [
                    'id' => $typeClass::id(),
                    'name' => static::BACK_LANG::get($typeClass::name()),
                    'description' => static::BACK_LANG::get($typeClass::description()),
                ];
            }
        }
        return $result;
    }

    public static function extensions(array $ids): array {
        $extensions = [];
        foreach ($ids as $id) {
            $typeClass = static::find($id);
            if ($typeClass) {
                $extensions[] = $typeClass::$extension;
            }
        }
        return $extensions;
    }
    public static function getIdBySlug($slug) {
        foreach (static::TYPES as $type) {
            if ($type::$slug == $slug) {
                return $type::id();
            }
        }

        return null;
    }

    public static function getIdAndNameAndOrderUsingId($id) {

        foreach (static::TYPES as $type) {
            if ($type::id() == $id) {
                return [
                    'id' => $type::id(),
                    'name' => static::BACK_LANG::get($type::name()),
                    'order' => $type::$order,
                ];
            }
        }
        return null;
    }


    public static function getIdAndNameAndOrder(): array {
        $idAndNameAndOrders = [];

        foreach (static::TYPES as $typeClass) {
            array_push($idAndNameAndOrders, [
                'id' => $typeClass::id(),
                'name' => static::BACK_LANG::get($typeClass::name()),
                'order' => $typeClass::$order,
            ]);
        }

        return $idAndNameAndOrders;
    }

    public static function getNamesByComma(array $ids): string {
        $names = [];
        foreach (static::TYPES as $typeClass) {
            if (in_array($typeClass::id(), $ids)) {
                $names[] = static::BACK_LANG::get($typeClass::name());
            }
        }
        return implode(', ', $names);
    }

    public static function getIdAndNameAndOwnerUsingId($id) {

        foreach (static::TYPES as $type) {
            if ($type::id() == $id) {
                return [
                    'id' => $type::id(),
                    'name' => static::BACK_LANG::get($type::name()),
                    'owner' => $type::$owner,
                ];
            }
        }
        return null;
    }

    public static function getIdAndNameAndOwner(): array {
        $idAndNameAndOrders = [];

        foreach (static::TYPES as $typeClass) {
            array_push($idAndNameAndOrders, [
                'id' => $typeClass::id(),
                'name' => static::BACK_LANG::get($typeClass::name()),
                'owner' => $typeClass::$owner,
            ]);
        }

        return $idAndNameAndOrders;
    }
}
