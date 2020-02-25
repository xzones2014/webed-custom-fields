<?php namespace WebEd\Base\CustomFields\Support;

use WebEd\Base\CustomFields\Repositories\Contracts\FieldGroupRepositoryContract;
use WebEd\Base\CustomFields\Repositories\Contracts\FieldItemRepositoryContract;
use WebEd\Base\CustomFields\Repositories\FieldGroupRepository;
use WebEd\Base\CustomFields\Repositories\FieldItemRepository;
use Illuminate\Support\Facades\DB;

class ImportCustomFields
{
    /**
     * @var FieldGroupRepository
     */
    protected $fieldGroupRepository;

    /**
     * @var FieldItemRepository
     */
    protected $fieldItemRepository;

    public function __construct(
        FieldGroupRepositoryContract $fieldGroupRepository,
        FieldItemRepositoryContract $fieldItemRepository
    )
    {
        $this->fieldGroupRepository = $fieldGroupRepository;

        $this->fieldItemRepository = $fieldItemRepository;
    }

    public function import(array $fieldGroupsData)
    {
        DB::beginTransaction();
        foreach ($fieldGroupsData as $fieldGroup) {
            $id = $this->fieldGroupRepository
                ->create($fieldGroup);
            if (!$id) {
                DB::rollBack();
                return false;
            }
            $createItems = $this->createFieldItem(array_get($fieldGroup, 'items', []), $id);
            if (!$createItems) {
                DB::rollBack();
                return false;
            }
        }
        DB::commit();
        return true;
    }

    protected function createFieldItem(array $items, $fieldGroupId, $parentId = null)
    {
        foreach ($items as $item) {
            $item['field_group_id'] = $fieldGroupId;
            $item['parent_id'] = $parentId;
            $id = $this->fieldItemRepository
                ->create($item);
            if (!$id) {
                return false;
            }
            $createChildren = $this->createFieldItem(array_get($item, 'children', []), $fieldGroupId, $id);
            if (!$createChildren) {
                return false;
            }
        }
        return true;
    }
}
