<?php

namespace Eyf\RAdmin\Http\Controllers;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Kris\LaravelFormBuilder\FormBuilderTrait;
//
use Eyf\RAdmin\Forms\DeleteForm;
use Eyf\RAdmin\Forms\SubmitCancelForm;
use Eyf\RAdmin\ResourceService;

abstract class ResourceController extends AdminController
{
    use FormBuilderTrait, AuthorizesRequests;

    public $redirectTo = 'index';
    public $orderBy    = 'updated_at';
    public $orderDir   = 'desc';
    public $perPage    = 10;
    public $columns    = [];
    public $views      = [];

    public function __construct(ResourceService $resource)
    {
        parent::__construct($resource);

        $this->resource->setController($this);

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
        $this->resource->setRequest($request);
        $this->canViewParents($request);
    }

    protected function after (Request $request, Response $response)
    {
        //
    }

    protected function canViewParents (Request $request)
    {
        foreach ($this->resource->parents() as $parent) {
            if (!$request->user()->can('view', $request->route($parent))) {
                throw new AccessDeniedHttpException('User is not allowed to view `' . $parent . '` parent resource');
            }
        }
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index (Request $request)
    {
        $this->authorize('view', $this->resource->modelClassName());

        $orderBy = $request->has('order') ? $request->input('order') : $this->orderBy;
        $orderDir = $request->input('dir') ? $request->input('dir') : $this->orderDir;

        $model = $this->resource->model();

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
        $this->authorize('create', $this->resource->modelClassName());

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

        $form = $this->form($this->resource->form());
        if (!$form->isValid()) {
            return redirect()
                ->back()
                ->withErrors($form->getErrors())
                ->withInput()
                ->with('flash_status', 'error')
                ->with('flash', $this->resource->trans('messages.errors.store'))
            ;
        }

        $model = $this->resource->model()->create($request->all());

        // After
        $this->afterCreate($model, $request);
        $this->afterSave($model, $request);

        $flash = $this->resource->trans('messages.success.store', ['resource' => $this->modelToString($model)]);

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
        $model = $this->resource->model();

        return redirect($this->resource->route('edit', [$model]));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit (Request $request)
    {
        $model = $this->resource->model();
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

        $model = $this->resource->model();
        $this->authorize('update', $model);

        $form = $this->getForm($request, $model);

        if (!$form->isValid()) {
            return redirect()
                ->back()
                ->withErrors($form->getErrors())
                ->withInput()
                ->with('flash_status', 'error')
                ->with('flash', $this->resource->trans('messages.errors.update'))
            ;
        }

        $model->fill($request->all());
        $model->save();

        // After
        $this->afterSave($model, $request);

        $flash = $this->resource->trans('messages.success.update', ['resource' => $this->modelToString($model)]);

        return $this->redirectTo($request, $model, $flash);
    }

    /**
     * Confirm removal of the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function delete (Request $request)
    {
        $model = $this->resource->model();
        $this->authorize('delete', $model);

        $url = $this->resource->route('destroy', $request->route()->parameters());

        $form = $this->form(DeleteForm::class, compact('url', 'model'));

        return $this->render('delete', compact('model', 'form'), $request);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy (Request $request)
    {
        $model = $this->resource->model();
        $this->authorize('delete', $model);

        $model->delete();

        if ($request->ajax()) {
            return response()->json();
        }

        if ($request->input('_redirect') === 'back') {
            return redirect()->back();
        }

        $flash = $this->resource->trans('messages.success.destroy', ['resource' => $this->modelToString($model)]);

        return $this->redirectTo($request, $model, $flash);
    }

    protected function view ($template, $data, Request $request)
    {
        $view = parent::view($template, $data, $request);

        $routeParams = $request->route()->parameters();

        $view->with(array_merge([
            'resource' => [
                'name' => $this->resource->name(),
                'singular' => $this->resource->singular(),
                'plural' => $this->resource->plural(),
            ],
            'routes' => [
                'edit' => $this->resource->routeName('edit'),
                'destroy' => $this->resource->routeName('destroy'),
            ],
            'urls' => [
                'index' => $this->resource->route('index', $routeParams),
                'create' => $this->resource->route('create', $routeParams),
            ],
            'routeParams' => $routeParams,
        ], $routeParams));

        if (isset($view['model'])) {
            $view->with('resource.to_string', $this->modelToString($view['model']));
        }

        return $view;
    }

    protected function render ($action, $data, Request $request)
    {
        $data['title'] = $this->resource->title($action);
        return $this->view($this->resource->template($action), $data, $request);
    }

    protected function modelToString (Model $model)
    {
        $name = isset($model->name) ? $model->name : ('#' . $model->id);
        return $this->resource->singular() . ' <strong>' . $name . '</strong>';
    }

    protected function getForm (Request $request, $model = null, $formData = [])
    {
        $method = $model ? 'PUT' : 'POST';
        $params = $request->route()->parameters();

        if ($model) {
            $params[$this->resource->name()] = $model->getKey();
            $action = 'update';
        } else {
            $action = 'store';
        }
        $url = $this->resource->route($action, $params);

        if (!$url) {
            throw new \Exception('Failed to create \'' . $action . '\' Form action url (associated route name was not found in Router). If nested Resource, have you declared `getResourceNamespace()`?');
        }

        $form = $this->form($this->resource->form(), compact('method', 'url', 'model'), $formData);

        if ($values = $form->getData('values')) {
            foreach ($values as $key => $value) {
                $form->getField($key)->setValue($value);
            }
        }

        $form->add('submit', 'form', [
            'label' => false,
            'class' => SubmitCancelForm::class,
            'data' => ['action' => $method === 'POST' ? 'create' : 'edit']
        ]);

        return $form;
    }

    protected function redirectTo (Request $request, $model, $flash)
    {
        $params = $request->route()->parameters();

        if ($this->redirectTo === 'edit') {
            $params[$this->resource->name()] = $model->getKey();
        } else {
            unset($params[$this->resource->name()]);
        }

        if (count($params)) {
            return redirect($this->resource->route($this->redirectTo, $params));
        }

        return redirect($this->resource->route($this->redirectTo))
            ->with('flash_status', 'success')
            ->with('flash', $flash)
        ;
    }

    protected function getIndexActions ()
    {
        return [
            'edit' => $this->resource->routeName('edit'),
            'delete' => $this->resource->routeName('delete'),
        ];
    }

    protected function beforeSave (Request $request)
    {
        $params = $request->all();

        foreach ($params as $key => $val) {
            if ($val === '') {
                unset($request[$key]);
            }
        }
    }

    protected function afterSave ($model, Request $request)
    {
        //
    }

    protected function afterCreate ($model, Request $request)
    {
        //
    }
}
