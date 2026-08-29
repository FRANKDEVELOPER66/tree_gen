<?php
require_once __DIR__ . '/../includes/app.php';

use MVC\Router;
use Controllers\AppController;
use Controllers\ArbolController;
use Controllers\PersonasController;
use Controllers\UnionesController;
use Controllers\FiliacionesController;

$router = new Router();
$router->setBaseURL('/' . $_ENV['APP_NAME']);

// ── Tu plantilla base ────────────────────────────────────────────────────
$router->get('/', [AppController::class, 'index']);

// ── Paginas del arbol genealogico ───────────────────────────────────────
$router->get('/arbol', [ArbolController::class, 'index']);
$router->get('/personas', [PersonasController::class, 'index']);

// ── API: arbol ───────────────────────────────────────────────────────────
$router->get('/api/arbol/nucleo', [ArbolController::class, 'nucleoAPI']);
$router->get('/api/arbol/raiz', [ArbolController::class, 'raizAPI']);
$router->post('/api/arbol/raiz', [ArbolController::class, 'fijarRaizAPI']);
$router->get('/api/personas/buscar', [ArbolController::class, 'buscarAPI']);

// ── API: personas ────────────────────────────────────────────────────────
$router->get('/api/personas/listar', [PersonasController::class, 'listarAPI']);
$router->get('/api/personas/detalle', [PersonasController::class, 'detalleAPI']);
$router->post('/api/personas/guardar', [PersonasController::class, 'guardarAPI']);
$router->post('/api/personas/modificar', [PersonasController::class, 'modificarAPI']);
$router->post('/api/personas/eliminar', [PersonasController::class, 'eliminarAPI']);
$router->post('/api/personas/agregar-foto', [PersonasController::class, 'agregarFotoAPI']);
$router->post('/api/personas/eliminar-foto', [PersonasController::class, 'eliminarFotoAPI']);

// ── API: uniones ─────────────────────────────────────────────────────────
$router->post('/api/uniones/guardar', [UnionesController::class, 'guardarAPI']);
$router->post('/api/uniones/modificar', [UnionesController::class, 'modificarAPI']);
$router->post('/api/uniones/eliminar', [UnionesController::class, 'eliminarAPI']);

// ── API: filiaciones ─────────────────────────────────────────────────────
$router->post('/api/filiaciones/guardar', [FiliacionesController::class, 'guardarAPI']);
$router->post('/api/filiaciones/eliminar', [FiliacionesController::class, 'eliminarAPI']);


$router->get('/api/personas/hijos', [PersonasController::class, 'hijosAPI']);
$router->get('/api/personas/parejas', [PersonasController::class, 'parejasAPI']);
$router->get('/api/personas/uniones', [PersonasController::class, 'unionesAPI']);
$router->get('/api/personas/hermanos', [PersonasController::class, 'hermanosAPI']);
$router->get('/api/personas/progenitores', [PersonasController::class, 'progenitoresAPI']);

// Comprueba y valida las rutas, que existan y les asigna las funciones del Controlador
$router->comprobarRutas();
