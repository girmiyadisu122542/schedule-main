<?php

namespace Helper\SideBar;

class SideBarItem {

    /**
     * The name of the sidebar
     * this is a translation key
     *
     * @var string
     */
    public $name;

    /**
     * The icon that will be
     * use to represent the sidebar
     *
     * @var string
     */
    public $icon;

    /**
     * The path of the current
     * side bar item in the front router
     *
     * @var ?string|array<int, \Helper\SideBar\SideBarItem>
     */
    public $path;

    /**
     * The subItems of the current sidebar
     * if any
     *
     * @var ?array<int, \Helper\SideBar\SideBarItem>
     */
    public $subItems = null;

    public function __construct(string $name, string $icon, mixed $path = null, ?array $subItems = null) {
        $this->name = $name;
        $this->icon = $icon;
        $this->path = $path;
        $this->subItems = $subItems;

        if ($subItems == null && is_array($path)) {
            $subItems = $path;
            $path = '';
        }
    }

    /**
     * Creates a new Sidebar item instance
     *
     * @param string $name
     * @param string $icon
     * @param mixed $path
     * @param ?array<int, \Helper\SideBar\SideBarItem> $subItems
     *
     * @return \Helper\SideBar\SideBarItem
     */
    public static function make(string $name, string $icon, mixed $path = null, ?array $subItems = null): SideBarItem {
        return new static($name, $icon, $path, $subItems);
    }

    /**
     * Create a new SideBar dynamically
     * the function name will be the name
     * of the sidebar
     *
     * @param string $name
     * @param array $arguments
     *
     * @return \Helper\SideBar\SideBarItem
     */
    public static function __callStatic($name, $arguments) {
        return SideBarItem::make($name, ...$arguments);
    }

    /**
     * Parses the sidebars
     * and sub items in to an array
     *
     * @return array<int, mixed>
     */
    public function toJson(): array {
        $json = [
            'name' => $this->name,
            'icon' => $this->icon,
        ];

        if ($this->path != null) {
            $json['path'] = $this->path;
        }

        if ($this->subItems != null) {
            $subItems = [];
            foreach ($this->subItems as $subItem) {
                array_push($subItems, $subItem->toJson());
            }

            $json['subItems'] = $subItems;
        }

        return $json;
    }
}