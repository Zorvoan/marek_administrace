<?php

namespace App\View\Components;

use App\Models\Category;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * The page shell: navigation bar (with the search form) around the content.
 */
class Layout extends Component
{
    public function __construct(public ?string $title = null) {}

    public function render(): View
    {
        return view('components.layout', [
            'categories' => Category::orderBy('name')->get(),
            'search' => $this->queryString('q'),
            'selectedCategory' => $this->queryString('category'),
        ]);
    }

    /**
     * A query-string value for pre-filling the search form. Anything that is
     * not a plain string (e.g. "?q[]=x") is ignored instead of breaking the page.
     */
    private function queryString(string $key): string
    {
        $value = request()->query($key);

        return is_string($value) ? $value : '';
    }
}
