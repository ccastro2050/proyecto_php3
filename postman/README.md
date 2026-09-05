# postman — la colección de la API, lista para importar

Esta API no tiene documentación interactiva integrada (PHP puro, sin
framework: no hay quién la genere — el contrato vive en
[6_contracts.md](../docs/spec_kit/versiones/v3_segundo_motor/6_contracts.md)).
**Postman cumple ese papel**: aquí está la colección con los seis recursos ya armados, para verlos y probarlos con clics.

## Cómo usarla (3 pasos)

1. Instale **Postman** (postman.com/downloads). Si le pide cuenta, puede
   usar la opción de cliente ligero sin registrarse.
2. **Import** (botón arriba a la izquierda) → arrastre el archivo
   `coleccion_v3.postman_collection.json` de esta carpeta.
3. Con el proyecto corriendo (`docker compose up -d`), abra cualquier
   petición y dele **Send**.

## El orden cuenta una historia, y hay que respetarlo

Las carpetas están numeradas, y esta vez el orden **no es solo pedagógico:
es obligatorio**.

Empiece por **Empresas** y **Personas**. Son las tablas de las que las demás
dependen, así que tienen que existir antes: un cliente necesita una persona.
Si lo intenta al revés, la base dice que no — y esa negativa también está en
la colección, como petición de ejemplo.

Después, **Clientes** y **Vendedores** (donde la llave la genera la base) y
al final **Facturas**, que necesita a los tres anteriores más un producto.

## Las peticiones que dan 409 no están rotas

Hay una en casi cada carpeta, y son las que más enseñan:

| Petición | Qué demuestra |
|---|---|
| Crear una empresa con un código que ya existe | La llave primaria la defiende la base |
| Crear un cliente apuntando a `NOEXISTE` | La API **no comprobó antes**: intentó, y tradujo el veredicto |
| Eliminar la persona `P001` | No se puede: ya es cliente. Y la API lo dice en español |
| Anular dos veces la misma factura | La regla la puso un procedimiento almacenado, no un `if` de PHP |

**Ninguna responde 500.** Un 500 diría «se nos dañó algo», y no se dañó nada:
la petición estaba bien escrita y lo que no cabía era en los datos. Ésa es la
diferencia que introduce esta versión.

## Úsela DOS VECES, una por motor

Es lo que la versión 3 viene a enseñar, y la colección es la forma más
cómoda de comprobarlo.

```powershell
$env:MOTOR = "postgres"
docker compose up -d --no-deps --force-recreate api-facturas
```

Vuelva a correr las mismas peticiones. **Las respuestas deben ser idénticas**,
incluidos los textos de los 409 — compárelos palabra por palabra.

Que digan lo mismo no es casualidad: MariaDB reporta esos tres casos con tres
códigos numéricos y PostgreSQL con dos SQLSTATE, uno de los cuales sirve para
dos casos opuestos. Emparejarlos es trabajo de los dos traductores de errores.

Lo único que cambia es la respuesta del **Diagnóstico**, que dice el motor. Y
los datos: son dos bases independientes, así que las fichas que cree con una
no aparecen con la otra.

## La carpeta 6 es la que hay que mirar con calma

Las facturas **no son un CRUD**, y la colección lo enseña por contraste:
compare esa carpeta con las cinco de arriba. No tiene PATCH —hay una
petición que lo intenta, y responde 405—, tiene una operación `/anular` que
ningún otro recurso tiene, y sus respuestas devuelven la factura completa.

Al crear una factura, **anote antes el stock de los productos**. El `total`
de la respuesta y el stock de después son la prueba de que hay lógica
corriendo dentro de la base de datos.

## La variable {{base}}

La colección usa `base` = `http://localhost:8086` (el proyecto del curso). Si
está probando **SU reconstrucción** (la de la
[GUIA_IA3](../docs/spec_kit/versiones/v3_segundo_motor/GUIA_IA3.md), que corre
con los puertos +100): clic en la colección → pestaña **Variables** → cambie
`base` a `http://localhost:8186`. Una sola edición y todas las peticiones
apuntan a su proyecto.

## Los números de ejemplo hay que ajustarlos

Las peticiones de cliente, vendedor y factura usan llaves de ejemplo (`5`,
`4`, `7`) que dependen de cuántas fichas haya creado usted. **Es a propósito
y no se puede evitar**: esas llaves las genera la base, así que nadie —ni
esta colección— puede saberlas de antemano.

Cuando cree una ficha, la respuesta le devuelve su llave. Úsela en las
peticiones siguientes. Que haya que hacerlo es, en sí mismo, la lección de
las llaves autogeneradas.
