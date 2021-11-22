<?php namespace Mmatovski\Mallextended;

use System\Classes\PluginBase;
use System\Classes\PluginManager;

use Schema;

use OFFLINE\Mall\Models\Category;
use OFFLINE\Mall\Models\Product;
use OFFLINE\Mall\Models\Variant;

class Plugin extends PluginBase
{
    public function registerComponents(){
        return [
            'Mmatovski\Mallextended\Components\ProductsListing' => 'productsListing'
        ];
    }

    public function registerSettings()
    {
    }

    public function boot(){
    	$pluginManager = PluginManager::instance();

    	if($pluginManager->findByIdentifier('Offline.Mall')){
    		Variant::extend(function($model){
    			// Set new field "Static Properties" in Variant
                if (Schema::hasTable('offline_mall_product_variants') && !Schema::hasColumn('offline_mall_product_variants', 'static_props')){
                    Schema::table('offline_mall_product_variants', function($table){
                        $table->text('static_props')->nullable();
                    });
                }

                if (Schema::hasTable('offline_mall_product_variants') && !Schema::hasColumn('offline_mall_product_variants', 'availability')){
                    Schema::table('offline_mall_product_variants', function($table){
                        $table->text('availability')->nullable();
                    });
                }

		        $model->addJsonable([
		            'static_props',
		        ]);

    		});

    		\OFFLINE\Mall\Models\Address::extend(function($model){
                if (Schema::hasTable('offline_mall_addresses') && !Schema::hasColumn('offline_mall_addresses', 'phone')){
                    Schema::table('offline_mall_addresses', function($table){
                        $table->string('phone')->nullable();
                    });
                }
    		
                $model->addFillable(['phone']);
                $model->rules['phone'] = 'required';
            });

            Category::extend(function($model){
                if (Schema::hasTable('offline_mall_categories') && !Schema::hasColumn('offline_mall_categories', 'meta_keywords')){
                    Schema::table('offline_mall_categories', function($table){
                        $table->text('meta_keywords')->nullable();
                    });
                }
            });


    		\OFFLINE\Mall\Controllers\Addresses::extendFormFields(function($form, $model, $context){
    			if (! $model instanceof \OFFLINE\Mall\Models\Address) return;

                $form->addFields([
                    'phone' => [
                        'label' => 'Telephone',
                        'type' => 'text',
                        'span' => 'auto',
                    ]
                ]);
    		});

            \OFFLINE\Mall\Controllers\Categories::extendFormFields(function($form, $model, $context){
                if (! $model instanceof Category) return;

                $form->addTabFields([
                    'meta_keywords' => [
                        'label' => 'Meta keywords',
                        'type' => 'textarea',
                        'span' => 'auto',
                        'tab' => 'offline.mall::lang.product.description',
                        'size' => 'small'
                    ]
                ]);
            });

    		\OFFLINE\Mall\Controllers\Products::extendFormFields(function($form, $model, $context){
    			if ($model instanceof Variant) {

    				if(!$form->isNested){
		                $form->addTabFields([
		                    'static_props' => [
		                        'label' => 'Variant Static Properties',
		                        'tab' => 'Static Properties',
		                        'type' => 'repeater',
		                        'span' => 'full',
		                        'form' => [
		                        	'fields' => [
			                        	'name' => [
			                        		'type' => 'text',
			                        		'span' => 'auto'
			                        	],
			                        	'value' => [
			                        		'type' => 'text',
			                        		'span' => 'auto'
			                        	]
			                        ]
		                        ],
		                        
		                    ]
		                ]);
		            }

	                $form->addFields([
	                    'availability' => [
	                        'label' => 'Shipping Availability',
	                        'type' => 'text',
	                        'span' => 'auto',
	                    ]
	                ]);
    			}
    		});
    	}
    }
}
