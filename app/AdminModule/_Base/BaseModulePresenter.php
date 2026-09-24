<?php

namespace App\AdminModule\Presenters;

use App\AdminModule\Traits\BaseAttachmentsTrait;
use App\AdminModule\Traits\BaseBulkActionsTrait;
use App\AdminModule\Traits\BaseContentsTrait;
use App\AdminModule\Traits\BaseFilesTrait;
use App\AdminModule\Traits\BaseImagesTrait;
use BaseEntity;
use Exception;
use FilesService;
use ImagesService;
use Nette\Application\AbortException;
use Nette\Application\Responses\FileResponse;
use Nette\Application\UI\Form;
use Nette\Database\Table\Selection;
use Nette\Utils\Image;
use Nette\Utils\Paginator;
use Nette\Utils\Random;
use Nette\Utils\Strings;
use Nette\Utils\UnknownImageFileException;
use ReflectionClass;
use ReflectionException;

abstract class BaseModulePresenter extends BasePresenter
{
    use BaseBulkActionsTrait;
    use BaseContentsTrait;
    use BaseAttachmentsTrait;
    use BaseImagesTrait;
    use BaseFilesTrait;

    /** @var string */
    protected $moduleName;

    /** @var array */
    protected $moduleActions = [
        'list',
        'create',
        'edit',
        'delete'
    ];

    /** @var Selection */
    protected $selection;

    /** @var \BaseRepository */
    public $repository;

    /** @var object */
    public $service;

    /** @var string */
    protected $orderBy = '';

    /** @var array */
    protected $moduleSortingOptions = [];

    /** @var array */
    protected $entities = [];

    /** @var BaseEntity */
    protected $entity;

    /** @var BaseEntity */
    protected $parentEntity;

    /** @var BaseEntity */
    protected $originEntity;

    /** @var array */
    protected $filters = [];


    /**
     * @throws ReflectionException
     */
    protected function startup()
    {
        parent::startup();

        $reflection = new ReflectionClass($this);

        // module name
        $this->moduleName = $this->trans('admin.modules.' . Strings::firstLower(str_replace('Presenter', '', $reflection->getShortName())));

        // set actual module for easier permission check
        $this->aps->setActualModuleId(str_replace('Presenter', '', $reflection->getShortName()));

        // filters
        if ($this->session->filters && isset($this->session->filters[$this->getName()]) && is_array($this->session->filters[$this->getName()])) {
            $this->filters = $this->session->filters[$this->getName()];
        }

        $this->startupBaseFilesTrait();
        $this->startupBaseImageTrait();
    }


    /**
     * @param string $action
     * @return bool
     */
    public function hasPermission(string $action): bool
    {
        if (!in_array($action, $this->moduleActions)) {
            return TRUE;
        }

        if ($this->aps->has($action)) {
            return TRUE;
        }

        return FALSE;
    }


    /**
     * @param string $action
     * @throws AbortException
     */
    public function checkPermission(string $action): void
    {
        if (!$this->hasPermission($action)) {
            $this->redirect('AccessDeny:');
        }
    }


    /**
     *
     */
    public function actionDefault()
    {
        $this->checkPermission('list');
    }


    /**
     * @param null $id
     * @throws AbortException
     */
    public function actionNew($id = NULL)
    {
        $this->checkPermission('create');

        $this->parentEntity = $id ? $this->repository->getEntity($this->repository->find($id)) : NULL;

        $this->setView('edit');
    }


    /**
     * @return array
     */
    public function getDefaultFormValues(): array
    {
        $values = $this->entity->getValues(TRUE);

        return $values;
    }

    /**
     * @param $id
     * @throws AbortException
     */
    public function actionEdit($id)
    {
        $this->checkPermission('edit');

        $this->entity = $this->repository->getEntity($this->repository->find($id));

        $this['editForm']->setDefaults($this->getDefaultFormValues());
    }


    /**
     * @param $id
     * @return void
     * @throws AbortException
     */
    public function actionCopy($id)
    {
        $this->checkPermission('create');

        $this->entity = $this->repository->getEntity($this->repository->find($id));

        if ($this->repository->isWithSystem()) {
            $this->entity->setValue('system', NULL);
        }

        $this['editForm']->setDefaults($this->getDefaultFormValues());

        $this->setView('edit');
    }


    /**
     * @param $id
     * @throws AbortException
     */
    public function actionDelete($id)
    {
        $this->checkPermission('delete');

        if (!$this->repository->delete($id)) {
            $this->flashMessage($this->trans('admin.messages.errorDelete'), 'danger');
            $this->redirect('default');
        }
        $this->cleanRelatedModuleCache();

        $this->flashMessage($this->trans('admin.messages.successDelete'), 'success');
        $this->redirect('default');
    }


    /**
     * @param $itemId
     * @param string $key
     * @param string $value
     * @throws AbortException
     */
    public function handleSetValue($itemId, string $key, string $value)
    {
        $this->checkPermission('create');

        if (!$entity = $this->repository->findEntity($itemId)) {
            $this->redirect('this');
        }

        $entity->setValue($key, $value);
        $this->repository->update($entity);
        $this->cleanRelatedModuleCache();

        $this->afterHandleSetValue($itemId, $key, $value);

        if ($this->isAjax()) {
            $this->redrawControl();
        } else {
            $this->redirect('this');
        }
    }


    /**
     * @param $itemId
     * @param string $key
     * @param string $value
     * @return void
     */
    public function afterHandleSetValue($itemId, string $key, string $value)
    {

    }


    /**
     * @param $itemId
     * @throws AbortException
     */
    public function handleMoveUp($itemId)
    {
        $this->checkPermission('edit');

        if ($this->repository->isSortable()) {
            $this->repository->moveUp($itemId);
            $this->cleanRelatedModuleCache();
        }

        if ($this->isAjax()) {
            $this->redrawControl('list');
            $this->redrawControl('flashMessages');
        } else {
            $this->redirect('this');
        }
    }


    /**
     * @param $itemId
     * @throws AbortException
     */
    public function handleMoveDown($itemId)
    {
        $this->checkPermission('edit');

        if ($this->repository->isSortable()) {
            $this->repository->moveDown($itemId);
            $this->cleanRelatedModuleCache();
        }

        if ($this->isAjax()) {
            $this->redrawControl('list');
            $this->redrawControl('flashMessages');
        } else {
            $this->redirect('this');
        }
    }


    /**
     * @param $itemId
     * @param $aboveId
     * @throws AbortException
     */
    public function handleMoveAbove($itemId, $aboveId)
    {
        $this->checkPermission('edit');

        if ($this->repository->isSortable()) {
            $this->repository->moveAbove($itemId, $aboveId);
            $this->cleanRelatedModuleCache();
        }

        if ($this->isAjax()) {
            $this->redrawControl('list');
            $this->redrawControl('flashMessages');
        } else {
            $this->redirect('this');
        }
    }


    /**
     * @param $itemId
     * @param $belowId
     * @throws AbortException
     */
    public function handleMoveBelow($itemId, $belowId)
    {
        $this->checkPermission('edit');

        if ($this->repository->isSortable()) {
            $this->repository->moveBelow($itemId, $belowId);
            $this->cleanRelatedModuleCache();
        }

        if ($this->isAjax()) {
            $this->redrawControl('list');
            $this->redrawControl('flashMessages');
        } else {
            $this->redirect('this');
        }
    }


    /**
     *
     */
    public function handleResetFilters()
    {
        $this['filters']->resetFilters();
    }


    /**
     *
     */
    public function beforeRender()
    {
        $this->template->moduleName = $this->moduleName;
        $this->template->moduleActionName = (($this->getAction() == 'default') ? '' : $this->translator->translate('admin.actions.' . $this->getAction())) . ($this->entity && isset($this->entity->name) ? ' - ' . $this->entity->name : '');

        $this->template->isSortableModule = $this->repository?->isSortable();

        $this->template->filters = array_filter($this->filters, function ($v) {
            return ((!is_null($v) && $v != "") || $v == "0");
        });

        if ($this->entity) {
            $this->template->item = $this->entity;
        }

        $this->template->getLatte()->addProvider('formsStack', [$this['editForm']]);

        parent::beforeRender();
    }


    /**
     *
     */
    public function renderDefault()
    {
        if (!$this->repository) {
            return;
        }

        $this['filters-form']->setDefaults($this->filters);

        $this->selection = $this->repository->findAll();

        $this->applyFilters();
        $this->applyPaging();
        $this->applySorting();

        $this->entities = $this->repository->getEntities($this->selection);

        $this->template->items = $this->entities;
    }


    /**
     *
     */
    public function applyFilters()
    {
        $this['filters']->applyFilters($this->selection);
    }


    /**
     *
     */
    public function applyPaging()
    {
        $this['paginator']->applyPaging($this->selection);
    }

    /**
     *
     */
    public function applySorting()
    {
        $this['sorting']->applySorting($this->selection);
    }


    /**
     * @param $itemId
     * @throws AbortException
     */
    public function handleDelete($itemId)
    {
        $this->actionDelete($itemId);
    }


    /**
     * @return Form
     */
    protected function createComponentEditForm(): Form
    {
        $form = new Form();
        $form->setTranslator($this->translator);

        $form = $this->setFormFields($form);

        if ($this->repository->isWithImages()) {
            $form->addHidden('temp_images_directory', $this->imagesComponentTempDir);
        }

        if ($this->repository->isWithFiles()) {
            $form->addHidden('temp_files_directory', $this->filesComponentTempDir);
        }

        $form->addSubmit('submit', 'admin.formButtons.save');
        $form->addSubmit('submit_close', 'admin.formButtons.saveAndClose');

        $form->onSuccess[] = [$this, 'editFormSubmitted'];

        return $form;
    }


    /**
     * @param Form $form
     * @return Form
     */
    protected function setFormFields(Form $form): Form
    {
        $fields = $form->getComponents();

        // meta
        if ($this->repository->isWithMetaTitle() && !isset($fields['meta_title'])) {
            $form->addText('meta_title', 'admin.terms.metaTitle');
        }

        if ($this->repository->isWithMetaDesc() && !isset($fields['meta_desc'])) {
            $form->addTextArea('meta_desc', 'admin.terms.metaDesc');
        }

        if ($this->repository->isWithMetaImage() && !isset($fields['meta_image'])) {
            $form->addUpload('meta_image', 'admin.terms.image');
        }

        //active
        if ($this->repository->isWithActiveState() && !isset($fields['is_active'])) {
            $form->addSelect('is_active', $this->trans('admin.terms.isActive'), $this->global['bool'])->setTranslator(NULL);
        }
        if ($this->repository->isWithActiveDate() && !isset($fields['is_active_from'])) {
            $form->addText('is_active_from', 'admin.terms.from');
        }
        if ($this->repository->isWithActiveDate() && !isset($fields['is_active_to'])) {
            $form->addText('is_active_to', 'admin.terms.to');
        }

        return $form;
    }

    /**
     * @param Form $form
     * @throws AbortException
     * @throws UnknownImageFileException
     */
    public function editFormSubmitted(Form $form)
    {
        $this->beforeGetFormValues($form);

        $values = $this->getEditFormValues($form, $this->getAction());

        if ($this->entity) {
            $this->originEntity = clone $this->entity;
        }

        $this->beforeEntitySave($this->entity, $form);

        if ($this->getAction() == 'copy') {
            $copyFromEntity = $this->entity;
        }

        if ($this->getAction() == 'new') {
            $this->entity = $this->processCreateForm($values);
        } elseif ($this->getAction() == 'edit' && $this->entity) {
            $this->entity = $this->processEditForm($this->entity, $values);
        } elseif ($this->getAction() == 'copy' && $this->entity) {
            $this->entity = $this->processCreateForm($values);
        }

        $this->processImages($this->entity, $form, $this->repository);
        $this->processFiles($this->entity, $form, $this->repository);
        $this->processRelations($this->entity, $form);

        $this->afterEntitySave($this->entity, $form);

        if ($this->getAction() == 'new') {
            $this->afterEntityCreate($this->entity, $form);
        }

        if ($this->getAction() == 'edit') {
            $this->afterEntityUpdate($this->entity, $form);
        }

        if ($this->getAction() == 'copy') {
            $this->afterEntityCopy($copyFromEntity, $this->entity, $form);
        }

        $this->flashMessage($this->trans('admin.messages.successSave'), 'success');

        if ($form['submit']->isSubmittedBy()) {
            $this->redirect('edit', $this->entity->id);
        } else {
            $this->redirect('default');
        }
    }


    /**
     * @param array $values
     * @return BaseEntity
     */
    protected function processCreateForm(array $values): BaseEntity
    {
        return $this->repository->getEntity($this->repository->find($this->repository->insert($values)->id));
    }


    /**
     * @param BaseEntity $entity
     * @param array $values
     * @return BaseEntity
     */
    protected function processEditForm(BaseEntity $entity, array $values): BaseEntity
    {
        $entity->setValues($values);
        $this->repository->update($entity);

        return $entity;
    }


    /**
     * @param Form $form
     * @param string $action
     * @return array
     * @throws UnknownImageFileException
     */
    public static function getEditFormValues(Form $form, string $action): array
    {
        return self::prepareFormValues($form->getValues(TRUE), $form->getHttpData(), $action);
    }


    /**
     * @param array $values
     * @param array $httpData
     * @param string $action
     * @return array
     * @throws UnknownImageFileException
     * @throws \Nette\Utils\ImageException
     */
    public static function prepareFormValues(array $values, array $httpData, string $action): array
    {
        foreach ($values as $key => $val) {
            if ($key === 'lang_id') {
                $values[$key] = $val;
            } elseif (Strings::startsWith($key, 'datetime')) {
                $values[$key] = date('Y-m-d H:i:s', strtotime($val));
            } elseif (Strings::startsWith($key, 'date') || Strings::endsWith($key, '_date') || in_array($key, [
                    'valid_from',
                    'valid_to',
                    'is_active_from',
                    'is_active_to'
                ])) {
                $values[$key] = ($val) ? date('Y-m-d', strtotime($val)) : NULL;
            } elseif (Strings::startsWith($key, 'time') || Strings::endsWith($key, '_time')) {
                $values[$key] = date('Y-m-d H:i:s', strtotime($val));
            } elseif (Strings::startsWith($key, 'image') || Strings::endsWith($key, '_image') || Strings::startsWith($key, 'logo')) {
                if ($filename = ImagesService::saveImageUpload($val)) {
                    $values[$key] = $filename;
                } else {
                    unset($values[$key]);
                }
            } elseif (Strings::startsWith($key, 'file') && is_object($val)) {
                if ($filename = FilesService::saveFileUpload($val)) {
                    $values[$key] = $filename;
                } else {
                    unset($values[$key]);
                }
            } elseif (Strings::startsWith($key, 'tags')) { //tokenizer multiselect sends value over httpData
                $values[$key] = isset($httpData[$key]) ? implode(' ', $httpData[$key]) : '';
            }
        }

        if (isset($values['is_active']) && $values['is_active'] == FALSE) {
            $values['is_active_from'] = NULL;
            $values['is_active_to'] = NULL;
        }

        return $values;
    }


    /**
     * @param BaseEntity $entity
     * @param Form $form
     */
    protected function processRelations(BaseEntity $entity, Form $form)
    {
        $values = $form->getValues(TRUE);
        $relations = [];

        $this->database->beginTransaction();
        try {
            foreach ($values as $name => $value) {
                if (Strings::startsWith($name, 'rel_table__')) {
                    [$nothing, $table, $columnLeft, $columnRight] = explode('__', $name);
                    $relations[$table] = [
                        'columnLeft' => $columnLeft . '_id',
                        'columnRight' => $columnRight . '_id',
                        'values' => []
                    ];
                }
                if (Strings::startsWith($name, 'rel_values__') && $value) {
                    [$nothing, $table, $idRight] = explode('__', $name);
                    $relations[$table]['values'][] = $idRight;
                }
                if (Strings::startsWith($name, 'rel_multiselect__') && is_array($value) && count($value) > 0) {
                    [$nothing, $table] = explode('__', $name);
                    foreach ($value as $key => $idRight) {
                        $relations[$table]['values'][] = $idRight;
                    }
                }
            }

            foreach ($relations as $table => $relation) {
                if ($relation['columnLeft'] && $relation['columnRight']) {
                    // remove deleted
                    $this->database->table($table)
                        ->where($relation['columnLeft'], $entity->id)
                        ->where($relation['columnRight'] . ' NOT IN (?)', count($relation['values']) ? $relation['values'] : [0])
                        ->delete();

                    // buffer exists
                    $existsValues = array_values($this->database->table($table)
                        ->where($relation['columnLeft'], $entity->id)
                        ->fetchPairs($relation['columnRight'], $relation['columnRight']));

                    // new relations
                    $sets = [];
                    foreach ($relation['values'] as $value) {
                        if (!in_array($value, $existsValues)) {
                            $sets[] = [
                                $relation['columnLeft'] => $entity->id,
                                $relation['columnRight'] => $value
                            ];
                        }
                    }
                    if (count($sets)) {
                        $this->database->table($table)->insert($sets);
                    }
                }
            }

            $this->database->commit();
        } catch (Exception $e) {
            $this->database->rollBack();
        }
    }


    /**
     * @param Form $form
     * @return void
     */
    protected function beforeGetFormValues(Form $form)
    {
    }

    /**
     * @param BaseEntity|null $entity
     * @param Form $form
     */
    protected function beforeEntitySave(?BaseEntity $entity, Form $form)
    {
    }


    /**
     * @param BaseEntity $entity
     * @param Form $form
     */
    protected function afterEntitySave(BaseEntity $entity, Form $form)
    {
        $this->cleanRelatedModuleCache();

        if ($this->repository->containsColumn('contents_id') && !$entity->getContent() instanceof \ContentsEntity) {
            $content = $this->contentsService->createDefaultContents();
            $entity->contents_id = $content->id;
            $this->repository->update($entity);
        }
    }

    /**
     * @param BaseEntity $entity
     * @param Form $form
     */
    protected function afterEntityCreate(BaseEntity $entity, Form $form)
    {
    }


    /**
     * @param BaseEntity $entity
     * @param Form $form
     */
    protected function afterEntityUpdate(BaseEntity $entity, Form $form)
    {
    }


    /**
     * @param BaseEntity $copyFromEntity
     * @param BaseEntity $entity
     * @param Form $form
     * @return void
     */
    protected function afterEntityCopy(BaseEntity $copyFromEntity, BaseEntity $entity, Form $form)
    {
        $this->copyContents($copyFromEntity, $entity);

        $update = FALSE;
        foreach ($copyFromEntity->getValues() as $key => $val) {
            if (!$val) {
                continue;
            }

            if ((Strings::startsWith($key, 'image') && !Strings::startsWith($key, 'images')) || (Strings::endsWith($key, '_image') && !Strings::endsWith($key, '_images')) || Strings::startsWith($key, 'logo')) {
                $entity->$key = ImagesService::copyImage($copyFromEntity->$key) ?: '';
                //if copied
                if ($entity->$key && $entity->$key != $copyFromEntity->$key) {
                    $update = TRUE;
                    $this->filesDataService->copyData($copyFromEntity->$key, $entity->$key, \FilesDataEntity::TYPE_IMAGE);
                }
            } elseif ((Strings::startsWith($key, 'file') && !Strings::startsWith($key, 'files')) || (Strings::endsWith($key, '_file') && !Strings::endsWith($key, '_files'))) {
                $entity->$key = FilesService::copyFile($copyFromEntity->$key) ?: '';
                //if copied
                if ($entity->$key && $entity->$key != $copyFromEntity->$key) {
                    $update = TRUE;
                    $this->filesDataService->copyData($copyFromEntity->$key, $entity->$key, \FilesDataEntity::TYPE_FILE);
                }
            } elseif (Strings::startsWith($key, 'images') || Strings::endsWith($key, '_images')) {
                $images = $copyFromEntity->getImages($key);
                $newImages = [];
                if (is_array($images) && !empty($images)) {
                    foreach ($images as $file) {
                        if ($newImage = ImagesService::copyImage($file->file)) {
                            $this->filesDataService->copyData($file->file, $newImage, \FilesDataEntity::TYPE_IMAGE);
                            $file->file = $newImage;
                            $newImages[] = json_encode($file);
                        }
                    }
                }
                if (!empty($newImages)) {
                    $entity->$key = implode('; ', $newImages);
                    $update = TRUE;
                }
            } elseif (Strings::startsWith($key, 'files') || Strings::endsWith($key, '_files')) {
                $files = $copyFromEntity->getFiles($key);
                $newFiles = [];
                if (is_array($files) && !empty($files)) {
                    foreach ($files as $file) {
                        if ($newFile = ImagesService::copyImage($file->file)) {
                            $this->filesDataService->copyData($file->file, $newFile, \FilesDataEntity::TYPE_FILE);
                            $file->file = $newFile;
                            $newFiles[] = json_encode($file);
                        }
                    }
                }
                if (!empty($newFiles)) {
                    $entity->$key = implode('; ', $newFiles);
                    $update = TRUE;
                }
            }
        }
        if ($update) {
            $this->repository->update($entity);
        }
    }


    /**
     * @return string
     */
    public function getModuleName(): string
    {
        $reflection = new ReflectionClass($this);

        return Strings::firstLower(str_replace('Presenter', '', $reflection->getShortName()));
    }


    /**
     * @return array
     */
    public function getModuleActions(): array
    {
        return $this->moduleActions;
    }


    /**
     * @return FiltersControl
     */
    protected function createComponentFilters(): FiltersControl
    {
        $control = new FiltersControl($this->session, $this->translator);

        $control->setSessionKey($this->getName());
        $control->setOnFilterChange(function (FiltersControl $filtersControl, Form $filtersForm) {
            $this->getPaginatorControl()->setPage(1);
            $this->redirect('this');
        });

        return $control;
    }


    /**
     * @return SortingControl
     */
    protected function createComponentSorting(): SortingControl
    {
        $control = new SortingControl($this->session, $this->translator, $this->repository, $this->orderBy);
        $control->setSortingOptions($this->moduleSortingOptions);
        $control->setSessionKey($this->getName());
        $control->setOnSortingChange(function (string $sortingOption) {
            $this->getPaginatorControl()->setPage(1);
        });

        return $control;
    }


    /**
     * @return SortingControl
     */
    public function getSortingControl(): SortingControl
    {
        return $this['sorting'];
    }


    /**
     * @return PaginatorControl
     */
    protected function createComponentPaginator(): PaginatorControl
    {
        $paginator = new Paginator();
        $paginator->setItemCount($this->selection ? $this->selection->count('*') : 0);
        $control = new PaginatorControl();
        $control->setSession($this->session);
        $control->setSessionKey($this->getName());
        $control->setPaginator($paginator);

        return $control;
    }


    /**
     * @return PaginatorControl
     */
    public function getPaginatorControl(): PaginatorControl
    {
        return $this['paginator'];
    }


    /**
     * @return FilesControl
     */
    protected function createComponentFiles(): FilesControl
    {
        $control = new FilesControl($this->filesDataService);
        $control->setAction($this->getAction());
        $control->setTempDir($this->filesComponentTempDir);

        if ($this->entity) {
            $control->setFiles($this->entity->getFiles());
        }

        return $control;
    }


    /**
     * @return BaseActionsControl
     */
    protected function createComponentBaseActions(): BaseActionsControl
    {
        $control = new BaseActionsControl($this->aps, $this->repository);

        return $control;
    }


    /**
     * @param \Nette\Forms\Form $form
     * @param mixed $defaults
     * @return void
     */
    public function setFormDefaults(\Nette\Forms\Form $form, mixed $defaults)
    {
        foreach ($defaults as $key => $val) {
            //try catch for deleted item ids
            try {
                $form->setDefaults([$key => $val]);
            } catch (\Exception $e) {
            }
        }
    }
}
