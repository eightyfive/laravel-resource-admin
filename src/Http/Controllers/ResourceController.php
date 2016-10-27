<?php

namespace Eyf\RAdmin\Http\Controllers;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Kris\LaravelFormBuilder\FormBuilderTrait;
//
use Eyf\RAdmin\Forms\SubmitCancelForm;

abstract class ResourceController extends AdminController
{
    use FormBuilderTrait, AuthorizesRequests;

    protected $redirectTo = 'index';
    protected $orderBy    = 'updated_at';
    protected $orderDir   = 'desc';
    protected $perPage    = 10;
    protected $columns    = [];
    protected $views      = [];
    protected $crumbs;
    protected $namespace;
    protected $parents;
    protected $router;

    public function __construct(Router $router)
    {
        $this->router = $router;

        $that = $this;
        $this->middleware(function ($request, $next) use ($that) {
            $that->before($request);
            $response = $next($request);
            $that->after($request, $response);

            return $response;
        });
    }

    protected function before (Request $request)
    {
        $this->canViewParents($request);
    }

    protected function after (Request $request, Response $response)
    {
        //
    }

    protected function canViewParents (Request $request)
    {
        foreach ($this->getParents() as $parent) {
            if (!$request->user()->can('view', $request->route($parent))) {
                throw new AccessDeniedHttpException('User is not allowed to view `' . $parent . '` parent resource');
            }
        }
    }

    protected function getCrumbs ()
    {
        if (!isset($this->crumbs)) {
            $className = str_replace(config('radmin.namespaces.controllers'), '', get_class($this));
            $this->crumbs = explode('\\', $className);
        }
        return $this->crumbs;
    }

    protected function getNamespace ()
    {
        if (!isset($this->namespace)) {
            $crumbs = $this->getCrumbs();
            array_pop($crumbs);

            $this->namespace = array_map(function ($parent) {
                return str_slug($parent);
            }, $crumbs);
        }
        return $this->namespace;
    }

    protected function getParents ()
    {
        if (!isset($this->parents)) {
            $namespace = $this->getNamespace();
            array_shift($namespace); // Remove 'Admin' namespace (not a parent)

            $this->parents = $namespace;
        }
        return $this->parents;
    }

    protected function getFormClassName ()
    {
        return config('radmin.namespaces.forms') . $this->getModelShortName() . 'Form';
    }

    protected function getModelClassName ()
    {
        return config('radmin.namespaces.models') . $this->getModelShortName();
    }

    protected function getResourceNamespace ()
    {
        return implode('.', $this->getNamespace());
    }

    protected function getResourceToString ($model)
    {
        return $this->getResourceSingular() . ' <strong>#' . $model->id . '</strong>';
    }

    protected function getResourceSlug ()
    {
        return $this->getResourceParameter();
    }

    protected function getResourceParameter ()
    {
        return snake_case($this->getModelShortName());
    }

    protected function getResourceSingular ()
    {
        return title_case(
            str_replace(
                '_',
                ' ',
                snake_case($this->getModelShortName())
            )
        );
    }

    protected function getResourcePlural ()
    {
        return str_plural($this->getResourceSingular());
    }

    protected function getModelShortName ()
    {
        if (!isset($this->modelShortName)) {
            $crumbs = $this->getCrumbs();
            $shortName = array_pop($crumbs);

            $this->modelShortName = str_replace('Controller', '', $shortName);
        }
        return $this->modelShortName;
    }

    protected function getModel (Request $request)
    {
        if (!isset($this->model)) {
            $model = $request->route($this->getResourceParameter());
            if (!$model) {
                $modelClassName = $this->getModelClassName();
                $model = new $modelClassName;
            }
            $this->model = $model;
        }
        return $this->model;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index (Request $request)
    {
        $orderBy = $request->has('order') ? $request->input('order') : $this->orderBy;
        $orderDir = $request->input('dir') ? $request->input('dir') : $this->orderDir;

        $model = $this->getModel($request);

        // Build query
        $builder = $this->getIndexQuery($model->newQuery(), $request, $orderBy, $orderDir);
        if (!count($builder->getQuery()->orders)) {
            $builder->orderBy($orderBy, $orderDir);
        }

        // Paginator
        $paginator = $builder->paginate($this->perPage);
        $paginator->appends($request->except('page'));

        // View
        $data = [
            'orderBy'  => $orderBy,
            'orderDir' => $orderDir,
            'columns'  => $this->columns,
            'dates'    => $model->getDates(),
            'actions'  => $this->getIndexActions(),
            'models'   => $paginator,
        ];

        return $this->render('index', $data, $request);
    }

    protected function getIndexQuery (Builder $query, Request $request, $orderBy, $orderDir)
    {
        return $query;
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create (Request $request)
    {
        $this->authorize('create', $this->getModelClassName());

        $form = $this->getForm($request);
        return $this->render('create', compact('form'), $request);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store (Request $request)
    {
        // Before
        $this->beforeSave($request);

        $form = $this->form($this->getFormClassName());
        if (!$form->isValid()) {
            return redirect()
                ->back()
                ->withErrors($form->getErrors())
                ->withInput()
                ->with('flash_status', 'error')
                ->with('flash', $this->trans('messages.errors.store'))
            ;
        }

        $model = $this->getModel($request)->create($request->all());

        // After
        $this->afterCreate($model, $request);
        $this->afterSave($model, $request);

        $flash = $this->trans('messages.success.store', [':resource' => $this->getResourceToString($model)]);

        return $this->redirectTo($request, $model, $flash);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show (Request $request)
    {
        $model = $this->getModel($request);

        return redirect($this->makeUrl('edit', [$model]));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit (Request $request)
    {
        $model = $this->getModel($request);
        $this->authorize('update', $model);

        $form = $this->getForm($request, $model);

        return $this->render('edit', compact('model', 'form'), $request);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update (Request $request)
    {
        // Before
        $this->beforeSave($request);

        $model = $this->getModel($request);
        $this->authorize('update', $model);

        $form = $this->getForm($request, $model);

        if (!$form->isValid()) {
            return redirect()
                ->back()
                ->withErrors($form->getErrors())
                ->withInput()
                ->with('flash_status', 'error')
                ->with('flash', $this->trans('messages.errors.update'))
            ;
        }

        $model->fill($request->all());
        $model->save();

        // After
        $this->afterSave($model, $request);

        $flash = $this->trans('messages.success.update', [':resource' => $this->getResourceToString($model)]);

        return $this->redirectTo($request, $model, $flash);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy (Request $request)
    {
        $model = $this->getModel($request);
        $this->authorize('delete', $model);

        $model->delete();

        if ($request->ajax()) {
            return response()->json();
        }

        if ($request->input('_redirect') === 'back') {
            return redirect()->back();
        }

        $flash = $this->trans('messages.success.destroy', [':resource' => $this->getResourceToString($model)]);

        return $this->redirectTo($request, $model, $flash);
    }

    protected function view ($template, $data, Request $request)
    {
        $view = parent::view($template, $data, $request);

        $routeParams = $request->route()->parameters();

        $view->with([
            'resource_slug' => $this->getResourceSlug(),
            'resource_singular' => $this->getResourceSingular(),
            'resource_plural' => $this->getResourcePlural(),
            'routeParams' => $routeParams,
            'route_edit' => $this->makeRouteName('edit'),
            'route_destroy' => $this->makeRouteName('destroy'),
            'url_index' => $this->makeUrl('index', $routeParams),
            'url_create' => $this->makeUrl('create', $routeParams),
        ]);

        $view->with($routeParams);

        return $view;
    }

    protected function render ($action, $data, Request $request)
    {
        $data['title'] = $this->getTitle($action);
        return $this->view($this->makeTemplateName($action), $data, $request);
    }

    protected function getTitle($action)
    {
        $prefix = implode('.', ['messages', 'titles']);

        $transKey = $prefix . '.' . $this->getResourcePath($action);
        $title = $this->trans($transKey, [
            'resource_singular' => $this->getResourceSingular(),
            'resource_plural' => $this->getResourcePlural(),
        ]);

        if ($title === false) {
            $transKey = $prefix . '.' . $action;
            $title = $this->trans($transKey, [
                'resource_singular' => $this->getResourceSingular(),
                'resource_plural' => $this->getResourcePlural(),
            ]);
        }


        return $title;
    }

    protected function getForm(Request $request, $model = null, $formData = [])
    {
        $method = $model ? 'PUT' : 'POST';
        $params = $request->route()->parameters();

        if ($model) {
            $params[$this->getResourceSlug()] = $model->getKey();
            $action = 'update';
        } else {
            $action = 'store';
        }
        $url = $this->makeUrl($action, $params);

        if (!$url) {
            throw new \Exception('Failed to create \'' . $action . '\' Form action url (associated route name was not found in Router). If nested Resource, have you declared `getResourceNamespace()`?');
        }

        $form = $this->form($this->getFormClassName(), compact('method', 'url', 'model'), $formData);

        if ($values = $form->getData('values')) {
            foreach ($values as $key => $value) {
                $form->getField($key)->setValue($value);
            }
        }

        $form->add('submit', 'form', [
            'label' => false,
            'class' => SubmitCancelForm::class,
            'data' => [
                'isCreate' => $method === 'POST',
            ]
        ]);
        $this->setButtons($form, config('radmin.css.btn_primary'), config('radmin.css.btn_secondary'));

        return $form;
    }

    protected function getResourcePath($action, $resource = null)
    {
        if (!$resource) {
            $resource = $this->getResourceSlug();
        }

        return implode('.', array_filter([
            $this->getResourceNamespace(),
            $resource,
            $action
        ]));
    }

    protected function makeRouteName($action)
    {
        $name = $this->getResourcePath($action);
        return $this->router->has($name) ? $name : null;
    }

    protected function makeTemplateName($action)
    {
        if (in_array($action, $this->views)) {
            return $this->getResourcePath($action);
        }
        return 'radmin::' . implode('.', ['resource', $action]);
    }

    protected function makeUrl($action, $params = [])
    {
        $routeName = $this->makeRouteName($action);
        if (!$routeName) {
            return null;
        }
        return route($routeName, $params);
    }

    protected function redirectTo(Request $request, $model, $flash)
    {
        $params = $request->route()->parameters();
        if ($this->redirectTo === 'edit') {
            $params[$this->getResourceSlug()] = $model->getKey();
        } else {
            unset($params[$this->getResourceSlug()]);
        }

        if (count($params)) {
            return redirect($this->makeUrl($this->redirectTo, $params));
        }

        return redirect($this->makeUrl($this->redirectTo))
            ->with('flash_status', 'success')
            ->with('flash', $flash)
        ;
    }

    protected function getIndexActions()
    {
        return [
            'edit' => $this->makeRouteName('edit'),
            'delete' => $this->makeRouteName('destroy'),
        ];
    }

    protected function beforeSave(Request $request)
    {
        $params = $request->all();

        foreach ($params as $key => $val) {
            if ($val === '') {
                unset($request[$key]);
            }
        }
    }

    protected function afterSave($model, Request $request)
    {
        //
    }

    protected function afterCreate($model, Request $request)
    {
        //
    }

    protected function setButtons($form, $primary, $secondary)
    {
        $this->_setFieldClass($form->getField('submit'), 'submitButton', $primary);
        $this->_setFieldClass($form->getField('submit'), 'cancelButton', $secondary);
    }

    private function _setFieldClass($form, $field, $className)
    {
        $this->_mergeFieldAttr($form, $field, ['class' => $className]);
    }

    private function _mergeFieldAttr($form, $field, $attr)
    {
        $attr = array_merge(
            $form->getField($field)->getOption('attr'),
            $attr
        );
        $form
            ->getField($field)
            ->setOption('attr', $attr)
        ;
    }

    protected function trans($key, array $data)
    {
        $transKey = 'radmin::' . $key;
        $trans = trans($transKey, $data);

        if ($trans === $transKey) {
            return false;
        }

        return $trans;
    }
}
