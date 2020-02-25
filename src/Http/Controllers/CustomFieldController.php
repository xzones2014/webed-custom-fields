<?php namespace WebEd\Base\CustomFields\Http\Controllers;

use Illuminate\Http\Request;
use WebEd\Base\CustomFields\Http\DataTables\FieldGroupsListDataTable;
use WebEd\Base\CustomFields\Http\Requests\CreateFieldGroupRequest;
use WebEd\Base\CustomFields\Http\Requests\UpdateFieldGroupRequest;
use WebEd\Base\CustomFields\Repositories\Contracts\FieldGroupRepositoryContract;
use WebEd\Base\CustomFields\Repositories\FieldGroupRepository;
use WebEd\Base\CustomFields\Support\ExportCustomFields;
use WebEd\Base\CustomFields\Support\ImportCustomFields;
use WebEd\Base\Http\Controllers\BaseAdminController;
use WebEd\Base\Http\DataTables\AbstractDataTables;
use Yajra\Datatables\Engines\BaseEngine;

class CustomFieldController extends BaseAdminController
{
    protected $module = WEBED_CUSTOM_FIELDS;

    /**
     * @var FieldGroupRepository
     */
    protected $repository;

    /**
     * @param FieldGroupRepository $repository
     */
    public function __construct(FieldGroupRepositoryContract $repository)
    {
        parent::__construct();

        $this->repository = $repository;

        $this->middleware(function (Request $request, $next) {
            $this->getDashboardMenu($this->module);

            $this->breadcrumbs->addLink(trans('webed-custom-fields::base.page_title'), route('admin::custom-fields.index.get'));

            return $next($request);
        });
    }

    /**
     * @param AbstractDataTables|BaseEngine $dataTables
     * @return @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function getIndex(FieldGroupsListDataTable $dataTables)
    {
        $this->assets->addJavascriptsDirectly(asset('admin/modules/custom-fields/import-field-group.js'));

        $this->setPageTitle(trans('webed-custom-fields::base.page_title'));

        $this->dis['dataTable'] = $dataTables->run();

        return do_filter(BASE_FILTER_CONTROLLER, $this, WEBED_CUSTOM_FIELDS, 'index.get', $dataTables)->viewAdmin('index');
    }

    /**
     * @param AbstractDataTables|BaseEngine $dataTables
     * @return mixed
     */
    public function postListing(FieldGroupsListDataTable $dataTables)
    {
        $data = $dataTables->with($this->groupAction());

        return do_filter(BASE_FILTER_CONTROLLER, $data, WEBED_CUSTOM_FIELDS, 'index.post', $this);
    }

    /**
     * Handle group actions
     * @return array
     */
    protected function groupAction()
    {
        $data = [];
        if ($this->request->get('customActionType', null) === 'group_action') {
            if (!$this->userRepository->hasPermission($this->loggedInUser, ['your-permission'])) {
                return [
                    'customActionMessage' => trans('webed-acl::base.do_not_have_permission'),
                    'customActionStatus' => 'danger',
                ];
            }

            $ids = (array)$this->request->get('id', []);
            $actionValue = $this->request->get('customActionValue');

            switch ($actionValue) {
                case 'deleted':
                    if (!$this->userRepository->hasPermission($this->loggedInUser, ['your-permission'])) {
                        return [
                            'customActionMessage' => trans('webed-acl::base.do_not_have_permission'),
                            'customActionStatus' => 'danger',
                        ];
                    }

                    $ids = do_filter(BASE_FILTER_BEFORE_DELETE, $ids, WEBED_CUSTOM_FIELDS);

                    $result = $this->repository->deleteFieldGroup($ids);

                    do_action(BASE_ACTION_AFTER_DELETE, WEBED_CUSTOM_FIELDS, $ids, $result);
                    break;
                case 'activated':
                case 'disabled':
                    $result = $this->repository->updateMultiple($ids, [
                        'status' => $actionValue,
                    ]);
                    break;
                default:
                    return [
                        'customActionMessage' => trans('webed-core::errors.' . \Constants::METHOD_NOT_ALLOWED . '.message'),
                        'customActionStatus' => 'danger'
                    ];
                    break;
            }
            $data['customActionMessage'] = $result ? trans('webed-core::base.form.request_completed') : trans('webed-core::base.form.error_occurred');
            $data['customActionStatus'] = !$result ? 'danger' : 'success';

        }
        return $data;
    }

    /**
     * Update status
     * @param $id
     * @param $status
     * @return \Illuminate\Http\JsonResponse
     */
    public function postUpdateStatus($id, $status)
    {
        $data = [
            'status' => $status
        ];
        $result = $this->repository->update($id, $data);
        $msg = $result ? trans('webed-core::base.form.request_completed') : trans('webed-core::base.form.error_occurred');
        $code = $result ? \Constants::SUCCESS_NO_CONTENT_CODE : \Constants::ERROR_CODE;
        return response()->json(response_with_messages($msg, !$result, $code), $code);
    }

    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function getCreate()
    {
        do_action(BASE_ACTION_BEFORE_CREATE, WEBED_CUSTOM_FIELDS, 'create.get');

        $this->setPageTitle(trans('webed-custom-fields::base.form.create_field_group'));
        $this->breadcrumbs->addLink(trans('webed-custom-fields::base.form.create_field_group'));

        return do_filter(BASE_FILTER_CONTROLLER, $this, WEBED_CUSTOM_FIELDS, 'create.get')->viewAdmin('create');
    }

    public function postCreate(CreateFieldGroupRequest $request)
    {
        do_action(BASE_ACTION_BEFORE_CREATE, WEBED_CUSTOM_FIELDS, 'create.post');

        $data['created_by'] = $this->loggedInUser->id;

        $result = $this->repository->createFieldGroup($request->get('field_group', []));

        do_action(BASE_ACTION_AFTER_CREATE, WEBED_CUSTOM_FIELDS, $result);

        $msgType = !$result ? 'danger' : 'success';
        $msg = $result ? trans('webed-core::base.form.request_completed') : trans('webed-core::base.form.error_occurred');

        flash_messages()
            ->addMessages($msg, $msgType)
            ->showMessagesOnSession();

        if (!$result) {
            return redirect()->back()->withInput();
        }

        if ($this->request->has('_continue_edit')) {
            return redirect()->to(route('admin::custom-fields.field-group.edit.get', ['id' => $result]));
        }

        return redirect()->to(route('admin::custom-fields.index.get'));
    }

    /**
     * @param $id
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Http\RedirectResponse|\Illuminate\View\View
     */
    public function getEdit($id)
    {
        $item = $this->repository->find($id);

        if (!$item) {
            flash_messages()
                ->addMessages(trans('webed-custom-fields::base.item_not_exists'), 'danger')
                ->showMessagesOnSession();

            return redirect()->back();
        }

        $item = do_filter(BASE_FILTER_BEFORE_UPDATE, $item, WEBED_CUSTOM_FIELDS, 'edit.get');

        $this->setPageTitle(trans('webed-custom-fields::base.form.edit_field_group') . ' #' . $item->id);
        $this->breadcrumbs->addLink(trans('webed-custom-fields::base.form.edit_field_group'));

        $this->dis['object'] = $item;

        $this->dis['customFieldItems'] = json_encode($this->repository->getFieldGroupItems($id));

        return do_filter(BASE_FILTER_CONTROLLER, $this, WEBED_CUSTOM_FIELDS, 'edit.get', $id)->viewAdmin('edit');
    }

    /**
     * @param $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function postEdit(UpdateFieldGroupRequest $request, $id)
    {
        $item = $this->repository->find($id);

        if (!$item) {
            flash_messages()
                ->addMessages(trans('webed-core::base.item_not_exists'), 'danger')
                ->showMessagesOnSession();

            return redirect()->back();
        }

        $item = do_filter(BASE_FILTER_BEFORE_UPDATE, $item, WEBED_CUSTOM_FIELDS, 'edit.post');

        $result = $this->repository->updateFieldGroup($item, $request->get('field_group'));

        do_action(BASE_ACTION_AFTER_UPDATE, WEBED_CUSTOM_FIELDS, $id, $result);

        $msgType = !$result ? 'danger' : 'success';
        $msg = $result ? trans('webed-core::base.form.request_completed') : trans('webed-core::base.form.error_occurred');

        flash_messages()
            ->addMessages($msg, $msgType)
            ->showMessagesOnSession();

        if ($this->request->has('_continue_edit')) {
            return redirect()->back();
        }

        return redirect()->to(route('admin::custom-fields.index.get'));
    }

    /**
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteDelete($id)
    {
        $id = do_filter(BASE_FILTER_BEFORE_DELETE, $id, WEBED_CUSTOM_FIELDS);

        $result = $this->repository->deleteFieldGroup($id);

        do_action(BASE_ACTION_AFTER_DELETE, WEBED_CUSTOM_FIELDS, $id, $result);

        $msg = $result ? trans('webed-core::base.form.request_completed') : trans('webed-core::base.form.error_occurred');
        $code = $result ? \Constants::SUCCESS_NO_CONTENT_CODE : \Constants::ERROR_CODE;
        return response()->json(response_with_messages($msg, !$result, $code), $code);
    }

    /**
     * @param ExportCustomFields $exportSupport
     * @return \Illuminate\Http\JsonResponse
     */
    public function getExport(ExportCustomFields $exportSupport)
    {
        $ids = [];
        foreach ($this->repository->get(['id']) as $item) {
            $ids[] = $item->id;
        }
        $json = $exportSupport->export($ids);
        return response()->json($json, 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    public function postImport(ImportCustomFields $importCustomFields)
    {
        $json = $this->request->get('json_data');
        $result = $importCustomFields->import($json);
        $message = $result ? 'Field group imported' : 'Error occurred when import field group';
        return response_with_messages($message, !$result, ($result ? \Constants::SUCCESS_NO_CONTENT_CODE : \Constants::ERROR_CODE));
    }
}
