<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExpenseHead;

class ExpenseHeadController extends Controller
{
    public function index()
    {
        return ExpenseHead::all();
    }
}
