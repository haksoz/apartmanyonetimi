<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApartmentDataOperation;

class AdminDataOperationController extends Controller
{
    public function index()
    {
        $operations = ApartmentDataOperation::query()
            ->with('user')
            ->latest('id')
            ->paginate(30);

        return view('admin.data-operations.index', compact('operations'));
    }

    public function show(ApartmentDataOperation $dataOperation)
    {
        $dataOperation->load('user');

        return view('admin.data-operations.show', ['operation' => $dataOperation]);
    }
}
