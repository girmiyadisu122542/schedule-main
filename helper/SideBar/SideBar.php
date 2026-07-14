<?php

namespace Helper\SideBar;

class SideBar {
    /**
     * The title of the sidebar
     * this is a translation key
     *
     * @var string
     */
    public $title;

    /**
     * The module of the sidebar
     *
     * @var string|array|null
     */
    public $moduleCode;

    /**
     * The display order of the sidebar
     * group in the frontend menu
     *
     * @var ?int
     */
    public $order;

    /**
     * The list of
     * items in that sidebar
     *
     * @var array<int, \Helper\SideBar\SideBarItem>
     */
    public $items;

    public function __construct(string $title, array $items, string|array|null $moduleCode = null, ?int $order = null) {
        $this->title = $title;
        $this->items = $items;
        $this->moduleCode = $moduleCode;
        $this->order = $order;
    }

    /**
     * Creates a new Sidebar item instance
     *
     * @param string $title
     * @param array<int, \Helper\SideBar\SideBarItem> $items
     * @param string|array|null $moduleCode
     * @param ?int $order
     *
     * @return \Helper\SideBar\SideBar
     */
    public static function make(string $title, array $items, string|array|null $moduleCode = null, ?int $order = null): SideBar {
        return new static($title, $items, $moduleCode, $order);
    }

    /**
     * Parses the sidebars
     * and sub items in to an array
     *
     * @return array<int, mixed>
     */
    public function toJson(): array {
        $items = [];
        foreach ($this->items as $item) {
            array_push($items, $item->toJson());
        }

        $modules = is_array($this->moduleCode)
            ? array_values($this->moduleCode)
            : ($this->moduleCode !== null ? [$this->moduleCode] : []);

        return [
            'items' => $items,
            'title' => $this->title,
            'module' => $modules[0] ?? null,
            'modules' => $modules,
            'order' => $this->order,
        ];

    }

    /**
     * Create a new sidebar
     * and parse to an array
     *
     * @param array<int, \Helper\SideBar\SideBar>
     *
     * @return array<int, mixed>
     */
    public static function create(array $sideBars): array {
        $json = [];
        foreach ($sideBars as $sideBar) {
            array_push($json, $sideBar->toJson());
        }

        return $json;
    }
}