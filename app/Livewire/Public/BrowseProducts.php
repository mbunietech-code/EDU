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
        // Mbunie VPN gets its own card that leads to vpn.mbuniehub.com.
        $query = Product::published()->where('type', '!=', 'vpn')->withCount('plans');

        $term = mb_strtolower(trim($this->search));
        $showVpn = $this->getPage() === 1
            && ($term === '' || str_contains('mbunie vpn internet china travel', $term));
        $vpnProduct = $showVpn ? Product::where('type', 'vpn')->latest()->first() : null;

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

        return view('livewire.public.browse-products', [
            'products' => $products,
            'rates' => $rates,
            'showVpn' => $showVpn,
            'vpnProduct' => $vpnProduct,
        ]);
    }
}