<?php

namespace App\Livewire\Public;

use App\Models\Product;
use App\Services\CurrencyRateService;
use Livewire\Component;
use Livewire\WithPagination;

class BrowseProducts extends Component
{
    use WithPagination;

    public string $search = '';

    public string $sort = 'latest';

    protected $queryString = ['search', 'sort'];

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedSort(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $query = Product::published()->withCount('plans');

        if ($this->search !== '') {
            $query->where(function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                  ->orWhere('description', 'like', "%{$this->search}%");
            });
        }

        $query->when($this->sort === 'price_asc', fn ($q) => $q->orderBy('price'))
              ->when($this->sort === 'price_desc', fn ($q) => $q->orderByDesc('price'))
              ->when($this->sort === 'latest', fn ($q) => $q->latest())
              ->when($this->sort === 'featured', fn ($q) => $q->featured()->latest());

        $products = $query->paginate(12);

        $rates = app(CurrencyRateService::class)->rates();

        return view('livewire.public.browse-products', ['products' => $products, 'rates' => $rates]);
    }
}