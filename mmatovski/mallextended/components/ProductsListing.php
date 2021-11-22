<?php

namespace Mmatovski\Mallextended\Components;

use OFFLINE\Mall\Models\Category;
use OFFLINE\Mall\Models\Product;


class ProductsListing extends \Cms\Classes\ComponentBase{

	public $items;
	public $itemsCount;
	public $category;
    public $paginationInfo;
	public $perPage;
    public $sort;
    public $isSearchPage;
    public $useParentCategory;

	private $slug;


	public function componentDetails(){
        return [
            'name' => 'Product Listing',
            'description' => 'List products by their category.'
        ];
    }

	public function defineProperties(){
        return [
            'category'        => [
                'title'   => 'offline.mall::lang.common.category',
                'default' => null,
                'type'    => 'string',
            ],
            'setPageTitle'    => [
                'title'       => 'offline.mall::lang.components.products.properties.set_page_title.title',
                'description' => 'offline.mall::lang.components.products.properties.set_page_title.description',
                'default'     => '0',
                'type'        => 'checkbox',
            ],
            'perPage'         => [
                'title'       => 'offline.mall::lang.components.products.properties.per_page.title',
                'description' => 'offline.mall::lang.components.products.properties.per_page.description',
                'default'     => '10',
                'type'        => 'string',
            ],
            'sort'            => [
                'title'       => 'offline.mall::lang.components.products.properties.sort.title',
                'description' => 'offline.mall::lang.components.products.properties.sort.description',
                'default'     => 'id',
                'type'        => 'dropdown',
            ],
            'searchPage' => [
                'title'       => 'Is a Search Page?',
                'description' => 'Used for searchable products',
                'default'     => '0',
                'type'        => 'checkbox',
            ],
            'useParentCategory' => [
                'title'       => 'Use parent category?',
                'default'     => '0',
                'type'        => 'checkbox',
            ]

	    ];
	}

	public function onRun(){
        $this->setSearchable();

        if(! $this->isSearchPage || $this->isSearchPage == 0 ){
          $this->setCategory();
		  $this->page['category'] = $this->category;

		  if(! $this->category) return \Redirect::to('404');

          $this->setSeo();
        }
        
        $this->setComponentProperties();
        
        if(! $this->isSearchPage || $this->isSearchPage == 0)
            $this->setItems();
        else
            $this->setSearchableItems();

        $this->setPaginationInfo();

	}

    public function setSearchable(){
        $this->isSearchPage = $this->property('searchPage') ?: 0;
    }

    private function setComponentProperties(){
        if($this->property('setPageTitle') && ! $this->setSearchable)
            $this->page->title = $this->category->name;

        $this->perPage = isset($_GET['limit']) ? $_GET['limit'] : $this->property('perPage');
        $this->sort = isset($_GET['sort']) ? $_GET['sort'] : $this->property('sort');

        $this->sort = isset($this->sort) ? $this->sort : 'id';

        $this->useParentCategory = $this->property('useParentCategory');
    }

    private function setCategory(){

        $this->slug = $this->property('category') == ':slug' ? $this->param('slug') : $this->property('category');
        $this->category = Category::where('slug', $this->slug)->first();

    }

    private function setSeo(){

        if(isset($this->category->meta_title) && strlen($this->category->meta_title) > 0 )
            $this->page->meta_title = $this->category->meta_title;

        if(isset($this->category->meta_description) && strlen($this->category->meta_description) > 0 )
            $this->page->meta_description = $this->category->meta_description;

        if(isset($this->category->meta_keywords) && strlen($this->category->meta_keywords) > 0 )
            $this->page->meta_keywords = $this->category->meta_keywords;
    }

    private function setItems(){;

        $selfAndChildren = $this->category->getAllChildrenAndSelf()->pluck('id');

        if(! \Schema::hasColumn('offline_mall_products', $this->sort)) $this->sort = 'id';


        //$this->items = Product::join('offline_mall_category_product as c1', 'c1.product_id', '=', 'offline_mall_products.id')->whereIn('category_id', $selfAndChildren)->where('published', 1)->groupBy('offline_mall_products.id')->orderBy('offline_mall_products.' .  $this->sort, 'ASC')->paginate($this->perPage);

        $this->items = Product::
            join('offline_mall_category_product as c1', 'c1.product_id', '=', 'offline_mall_products.id')->
            whereIn('category_id', $selfAndChildren)->where('published', 1)->
            groupBy('offline_mall_products.id')->
            orderBy('offline_mall_products.' .  $this->sort, 'ASC')->
            select('offline_mall_products.*')->
            paginate($this->perPage);


        // $this->items = Product::whereHas('categories', function($q) use ($selfAndChildren){
        //     $q->whereIn('category_id', $selfAndChildren);
        // })->where('published', 1)->orderBy('offline_mall_products.' .  $this->sort, 'ASC')->paginate($this->perPage);

        if(isset($_GET['sort']))
            $this->items->appends(['sort' => $this->sort]);

        if(isset($_GET['limit']))
            $this->items->appends(['limit' => $this->perPage]);

        $this->itemsCount = count($this->items);
    }

    private function setSearchableItems(){

        if(! isset($_GET['q'])) return;

        $query = trim($_GET['q']);

        if(strlen($query) < 5) return;

        if(! \Schema::hasColumn('offline_mall_products', $this->sort)) $this->sort = 'offline_mall_products.id';

        $this->items = Product::whereHas('property_values', function($q) use ($query){
            $q->where('value', 'LIKE', "%${query}%");
        })->orWhere('user_defined_id', 'LIKE', "%${query}%")->
        orWhere('name', 'LIKE', "%${query}%")->
        orWhere('meta_keywords', 'LIKE', "%${query}%")->
        where('published', 1)->groupBy('offline_mall_products.id')->orderBy($this->sort, 'ASC')->paginate($this->perPage);

        //echo $query;

        if(isset($_GET['sort']))
            $this->items->appends(['sort' => $this->sort]);

        if(isset($_GET['limit']))
            $this->items->appends(['limit' => $this->perPage]);

        if(isset($_GET['q']))
            $this->items->appends(['q' => $_GET['q']]);

        $this->itemsCount = count($this->items);

    }

    private function setPaginationInfo(){

        if(! isset($this->items)) return;

        $prev = ($this->items->currentPage() - 1) * $this->items->perPage();
        $to = $prev + count($this->items->getCollection());

        if(isset($_GET['page']) && $_GET['page'] > 1){
            $from = (($this->items->currentPage() - 1) * $this->items->perPage()) + 1;
            $to = $prev + count($this->items->getCollection());
        }else{
            $from = 1;
            $to = count($this->items->getCollection());
        }

        $this->paginationInfo = [
            'from' => $from,
            'to' => $to,
            'total' => $this->items->total()
        ];
    }

}