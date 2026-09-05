# -*- coding: utf-8 -*-
"""LA PRUEBA DE LA VERSIÓN 3: el mismo guion, contra los dos motores.

Esto es lo único que la v3 agrega a las pruebas, y es todo lo que hacía falta.

    python pruebas_humo/humo_los_dos_motores.py

Qué hace, en tres pasos que se repiten dos veces:

  1. reinicia la API con `MOTOR=mariadb` (o `postgres`) y espera a que
     responda;
  2. corre `humo_front.py` **entero**, sin cambiarle una línea;
  3. compara.

Y aquí está lo importante: **`humo_front.py` no sabe nada de motores.** Llena
formularios, oprime botones y lee lo que sale en la pantalla. Que dé el mismo
resultado dos veces es la demostración de que el motor no se filtra hacia
arriba — porque si se filtrara, la pantalla se comportaría distinto y el
guion lo notaría.

Es también el examen del principio abierto/cerrado, hecho de la única forma
que vale: ejecutándolo. Un documento que diga «cambiar de motor no afecta a
las capas de arriba» es una promesa; esto es una comprobación.

Al final quedan las dos bases con los datos que la prueba fue dejando. Para
volver al punto de partida:

    docker compose down -v && docker compose up -d --build
"""
import subprocess
import sys
import time
import urllib.error
import urllib.request

# La consola de Windows no siempre usa UTF-8; sin esto, imprimir una flecha
# revienta el guion con un error que no tiene nada que ver con lo que se prueba.
sys.stdout.reconfigure(encoding="utf-8", errors="replace")

API = "http://localhost:8086"
MOTORES = ["mariadb", "postgres"]


def esperar_api(motor, segundos=180):
    """Espera a que la API responda Y esté en el motor que se pidió.

    Las dos condiciones hacen falta. Esperar solo a que responda no alcanza:
    el contenedor viejo puede seguir contestando unos segundos mientras el
    nuevo arranca, y entonces la prueba correría contra el motor anterior sin
    que nadie se enterara — que es justamente el error que este guion existe
    para no cometer.
    """
    for _ in range(segundos // 2):
        try:
            with urllib.request.urlopen(API + "/", timeout=5) as r:
                import json
                if json.loads(r.read()).get("motor") == motor:
                    return True
        except Exception:
            pass
        time.sleep(2)
    return False


resultados = {}

for motor in MOTORES:
    print()
    print("=" * 70)
    print("  ARRANCANDO LA API CONTRA " + motor.upper())
    print("=" * 70)

    # `--no-deps` para no reiniciar las bases: los datos se conservan entre
    # las dos corridas, que es lo realista. Cada corrida usa un sufijo nuevo
    # para sus fichas, así que no chocan.
    subprocess.run(
        ["docker", "compose", "up", "-d", "--no-deps", "--force-recreate",
         "api-facturas"],
        env={**__import__("os").environ, "MOTOR": motor},
        capture_output=True, text=True,
    )

    if not esperar_api(motor):
        print("  La API no llegó a responder en " + motor + ".")
        resultados[motor] = "no arrancó"
        continue

    print("  Lista. Corriendo la prueba de humo completa…")
    print()

    proceso = subprocess.run(
        [sys.executable, "pruebas_humo/humo_front.py"],
        env={**__import__("os").environ, "MOTOR_ESPERADO": motor},
    )
    resultados[motor] = "VERDE" if proceso.returncode == 0 else "ROJO"

print()
print("=" * 70)
print("  RESULTADO CONTRA LOS DOS MOTORES")
print("=" * 70)
for motor in MOTORES:
    print("  " + motor.ljust(12) + resultados.get(motor, "?"))

if all(r == "VERDE" for r in resultados.values()):
    print()
    print("  Los dos en verde: el mismo guion, llenando los mismos")
    print("  formularios, obtuvo el mismo resultado contra dos motores")
    print("  distintos. Eso es lo que la versión 3 vino a demostrar.")
    print()
    print("  Y fíjese en lo que NO hubo que hacer para lograrlo: cambiar")
    print("  un controlador, un servicio o una pantalla. Solo se agregaron")
    print("  seis repositorios y un traductor de errores.")
    raise SystemExit(0)

print()
print("  Alguno falló. Si uno está en verde y el otro en rojo, el problema")
print("  no es de las pruebas: es que algo del motor se filtró hacia arriba.")
raise SystemExit(1)
