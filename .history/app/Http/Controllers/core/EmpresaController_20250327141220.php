<?php

namespace App\Http\Controllers\core;

use App\Http\Controllers\Controller;
use App\Models\core\EmpresaUsuario;
use Illuminate\Http\Request;

class EmpresaController extends Controller
{

    public function rptEmpresas(Request $request)
    {

        $empresas = EmpresaUsuario::select(
            'empresa_database.id',
            'empresa_database.nombre_base'
        )
            ->join('empresa_usuario_database', 'empresa_database.id', '=', 'empresa_usuario_database.id_empresa_database')
            ->join('empresa_usuario', 'empresa_usuario_database.id_empresa_usuario', '=', 'empresa_usuario.id')
            ->join('core_usuario_conexion', 'empresa_database.id_conexion', '=', 'core_usuario_conexion.id_conexion')
            ->where('core_usuario_conexion.estado', 1)
            ->where('empresa_database.estado', 1)
            ->where('empresa_usuario_database.estado', 1)
            ->where('empresa_usuario.id', 1)
            ->get();

        return $empresas;
    }
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
