<?php namespace WebEd\Base\CustomFields\Support;

use WebEd\Base\CustomFields\Repositories\Contracts\FieldGroupRepositoryContract;
use WebEd\Base\CustomFields\Repositories\Contracts\FieldItemRepositoryContract;
use WebEd\Base\CustomFields\Repositories\FieldGroupRepository;
use WebEd\Base\CustomFields\Repositories\FieldItemRepository;

class ExportCustomFields
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

    /**
     * @param array $fieldGroupIds
     * @return array
     */
    public function export(array $fieldGroupIds)
    {
        $fieldGroups = $this->fieldGroupRepository
            ->getWhere([
                ['id', 'IN', $fieldGroupIds]
            ], ['id', 'title', 'status', 'order', 'rules'])
            ->toArray();

        foreach ($fieldGroups as &$fieldGroup) {
            $fieldGroup['items'] = $this->getFieldItems($fieldGroup['id']);
        }

        return $fieldGroups;
    }

    /**
     * @param $fieldGroupId
     * @param null $parentId
     * @return array
     */
    protected function getFieldItems($fieldGroupId, $parentId = null)
    {
        $fieldItems = $this->fieldItemRepository
            ->getWhere([
                'field_group_id' => $fieldGroupId,
                'parent_id' => $parentId
            ])
            ->toArray();

        foreach ($fieldItems as &$fieldItem) {
            $fieldItem['children'] = $this->getFieldItems($fieldGroupId, $fieldItem['id']);
        }

        return $fieldItems;
    }
}
