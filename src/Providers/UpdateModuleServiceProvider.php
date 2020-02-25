<?php namespace WebEd\Base\CustomFields\Providers;

use Illuminate\Support\ServiceProvider;

class UpdateModuleServiceProvider extends ServiceProvider
{
    protected $module = 'WebEd\Base\CustomFields';

    protected $moduleAlias = 'webed-custom-fields';

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        app()->booted(function () {
            $this->booted();
        });
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        register_module_update_batches($this->moduleAlias, [
            //'2.1.4' => __DIR__ . '/../../update-batches/2.1.4.php',
        ]);
    }

    protected function booted()
    {
        load_module_update_batches($this->moduleAlias);
    }
}
