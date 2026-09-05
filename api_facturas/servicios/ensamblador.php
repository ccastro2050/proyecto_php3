<?php
/**
 * Ensamblador — el ÚNICO lugar del sistema que conoce clases concretas.
 *
 * ======================================================================
 * ESTE ES EL ARCHIVO DE LA v3, Y CASI EL ÚNICO QUE CAMBIÓ
 * ======================================================================
 *
 * En la v1 era una función. En la v2 fueron seis, una por recurso, y todas
 * armaban la misma cadena `servicio → repositorio` con el único repositorio
 * que existía.
 *
 * Ahora hay dos repositorios por recurso —uno por motor— y estas mismas seis
 * funciones **escogen**. Eso es lo que convierte al ensamblador en una
 * FÁBRICA de verdad.
 *
 * Y aquí está el examen de la versión, que conviene verificar y no creer:
 * agregar PostgreSQL **no tocó ni un controlador, ni un servicio, ni una
 * pantalla**. Lo que se escribió fue:
 *
 *   · seis clases `Repositorio*Postgres` nuevas (cumpliendo las MISMAS
 *     interfaces, que tampoco cambiaron);
 *   · un traductor de errores para el motor nuevo;
 *   · y la función `elegirMotor()` de aquí abajo.
 *
 * Si hubiera hecho falta tocar un servicio, la v2 habría estado mal cortada.
 * A eso se le llama **principio abierto/cerrado**: el sistema quedó abierto a
 * agregarle un motor, y cerrado a que agregárselo obligue a reescribirlo.
 *
 * ======================================================================
 * POR QUÉ SIGUEN SIENDO SEIS FUNCIONES Y NO UNA CON UN `switch`
 * ======================================================================
 *
 * La tentación creció con el segundo motor: ahora se podría escribir
 * `crearRepositorio($recurso, $motor)` y armar el nombre de la clase con
 * texto — `"Repositorio{$recurso}{$motor}"`. Doce clases en cuatro líneas.
 *
 * Y sigue estando mal, por lo mismo que la API no es genérica (Artículo 10):
 *
 *   · con las clases escritas, PHP verifica los tipos: si
 *     `RepositorioClientePostgres` no cumpliera `IRepositorioCliente`, el
 *     error sale al llamar la función;
 *   · con nombres armados en texto, el error sale **en producción**, el día
 *     que alguien pida el recurso que nadie probó **con el motor que nadie
 *     probó** — y ahora las combinaciones sin probar son el doble;
 *   · y quien lea este archivo ve, de un vistazo, exactamente qué doce
 *     clases existen.
 *
 * Doce líneas aburridas y verificables antes que cuatro líneas ingeniosas
 * que fallan tarde.
 */

// Modo estricto de tipos (ver explicación completa en index.php):
declare(strict_types=1);

require_once __DIR__ . '/ServicioProducto.php';
require_once __DIR__ . '/ServicioEmpresa.php';
require_once __DIR__ . '/ServicioPersona.php';
require_once __DIR__ . '/ServicioCliente.php';
require_once __DIR__ . '/ServicioVendedor.php';
require_once __DIR__ . '/ServicioFactura.php';

require_once __DIR__ . '/../repositorios/RepositorioProductoMariaDB.php';
require_once __DIR__ . '/../repositorios/RepositorioEmpresaMariaDB.php';
require_once __DIR__ . '/../repositorios/RepositorioPersonaMariaDB.php';
require_once __DIR__ . '/../repositorios/RepositorioClienteMariaDB.php';
require_once __DIR__ . '/../repositorios/RepositorioVendedorMariaDB.php';
require_once __DIR__ . '/../repositorios/RepositorioFacturaMariaDB.php';

require_once __DIR__ . '/../repositorios/RepositorioProductoPostgres.php';
require_once __DIR__ . '/../repositorios/RepositorioEmpresaPostgres.php';
require_once __DIR__ . '/../repositorios/RepositorioPersonaPostgres.php';
require_once __DIR__ . '/../repositorios/RepositorioClientePostgres.php';
require_once __DIR__ . '/../repositorios/RepositorioVendedorPostgres.php';
require_once __DIR__ . '/../repositorios/RepositorioFacturaPostgres.php';

/**
 * Qué motor está activo: `'mariadb'` o `'postgres'`.
 *
 * Sale de la variable de entorno `MOTOR`, que pone el compose. Si no viene o
 * trae cualquier otra cosa, **se usa MariaDB** en vez de fallar: el motor por
 * defecto es una decisión de configuración, no una trampa para el que se
 * equivoque escribiendo.
 *
 * Fíjese en que la comparación es contra un valor escrito aquí. No se usa el
 * texto de la variable para armar nada — si alguien pone `MOTOR=oracle`, no
 * pasa nada raro: arranca en MariaDB.
 */
function motorActivo(): string
{
    return strtolower(trim((string) getenv('MOTOR'))) === 'postgres'
        ? 'postgres'
        : 'mariadb';
}

/**
 * Los tres datos de conexión del motor activo.
 *
 * Los dos juegos de credenciales llegan siempre por el entorno; esta función
 * escoge el que corresponde. Los valores por defecto apuntan a los puertos
 * PUBLICADOS de cada base, para poder correr la API sin Docker mientras las
 * bases sí están en Docker.
 *
 * @return array{0: string, 1: string, 2: string}
 */
function datosDeConexion(): array
{
    $usuario = getenv('DB_USUARIO') ?: 'paradigmas';
    $clave   = getenv('DB_CLAVE')   ?: 'paradigmas123';

    if (motorActivo() === 'postgres') {
        return [
            getenv('DB_DSN_POSTGRES')
                ?: 'pgsql:host=localhost;port=15464;dbname=bdfacturas_postgres_local',
            $usuario, $clave,
        ];
    }

    return [
        getenv('DB_DSN_MARIADB')
            ?: 'mysql:host=localhost;port=13328;dbname=bdfacturas_mariadb_local',
        $usuario, $clave,
    ];
}

// ======================================================================
// Una función por recurso. Fíjese en el tipo de retorno: siempre LA
// INTERFAZ, nunca la clase concreta — quien las llama no sabe, y no
// necesita saber, con qué motor quedó armado el servicio.
// ======================================================================

function crearServicioProducto(): IServicioProducto
{
    [$dsn, $usuario, $clave] = datosDeConexion();
    return new ServicioProducto(
        motorActivo() === 'postgres'
            ? new RepositorioProductoPostgres($dsn, $usuario, $clave)
            : new RepositorioProductoMariaDB($dsn, $usuario, $clave)
    );
}

function crearServicioEmpresa(): IServicioEmpresa
{
    [$dsn, $usuario, $clave] = datosDeConexion();
    return new ServicioEmpresa(
        motorActivo() === 'postgres'
            ? new RepositorioEmpresaPostgres($dsn, $usuario, $clave)
            : new RepositorioEmpresaMariaDB($dsn, $usuario, $clave)
    );
}

function crearServicioPersona(): IServicioPersona
{
    [$dsn, $usuario, $clave] = datosDeConexion();
    return new ServicioPersona(
        motorActivo() === 'postgres'
            ? new RepositorioPersonaPostgres($dsn, $usuario, $clave)
            : new RepositorioPersonaMariaDB($dsn, $usuario, $clave)
    );
}

function crearServicioCliente(): IServicioCliente
{
    [$dsn, $usuario, $clave] = datosDeConexion();
    return new ServicioCliente(
        motorActivo() === 'postgres'
            ? new RepositorioClientePostgres($dsn, $usuario, $clave)
            : new RepositorioClienteMariaDB($dsn, $usuario, $clave)
    );
}

function crearServicioVendedor(): IServicioVendedor
{
    [$dsn, $usuario, $clave] = datosDeConexion();
    return new ServicioVendedor(
        motorActivo() === 'postgres'
            ? new RepositorioVendedorPostgres($dsn, $usuario, $clave)
            : new RepositorioVendedorMariaDB($dsn, $usuario, $clave)
    );
}

function crearServicioFactura(): IServicioFactura
{
    [$dsn, $usuario, $clave] = datosDeConexion();
    return new ServicioFactura(
        motorActivo() === 'postgres'
            ? new RepositorioFacturaPostgres($dsn, $usuario, $clave)
            : new RepositorioFacturaMariaDB($dsn, $usuario, $clave)
    );
}
