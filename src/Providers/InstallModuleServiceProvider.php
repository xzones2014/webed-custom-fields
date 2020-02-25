<?php namespace WebEd\Base\CustomFields\Providers;

use Illuminate\Support\ServiceProvider;
use Schema;
use Illuminate\Database\Schema\Blueprint;

class InstallModuleServiceProvider extends ServiceProvider
{
    protected $moduleAlias = 'webed-custom-fields';

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {

    }

    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        app()->booted(function () {
            acl_permission()
                ->registerPermission('View custom fields', 'view-custom-fields', $this->moduleAlias)
                ->registerPermission('Create field group', 'create-field-groups', $this->moduleAlias)
                ->registerPermission('Edit field group', 'edit-field-groups', $this->moduleAlias)
                ->registerPermission('Delete field group', 'delete-field-groups', $this->moduleAlias);
        });
    }
}
