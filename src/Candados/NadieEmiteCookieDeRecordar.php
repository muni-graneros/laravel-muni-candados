<?php

declare(strict_types=1);

namespace Muni\Candados\Candados;

use Muni\Candados\Candado;
use PHPUnit\Framework\Assert;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Candado: ningún código del sistema vuelve a pedir la cookie de recordar.
 *
 * La batería no ejercita los caminos de acceso federado —ClaveÚnica, Keycloak—
 * ni cada formulario suelto, así que se mira el código fuente. Un
 * «remember: true» o un «Auth::attempt($credenciales, …)» que reaparezca en
 * app/ o en routes/ hace fallar la batería nombrando el archivo, en vez de
 * repartir cookies de catorce meses en silencio.
 */
final class NadieEmiteCookieDeRecordar extends Candado
{
    /**
     * Cada forma conocida de pedir la cookie, con cómo se la describe al fallar.
     */
    private const array PATRONES = [
        '/remember:\s*true/' => 'Auth::login(…, remember: true)',
        '/->boolean\(\s*[\'"]remember[\'"]\s*\)/' => "\$request->boolean('remember')",
        '/Auth::attempt\(\s*\$\w+\s*,/' => 'Auth::attempt($credenciales, …) con segundo argumento',
        '/->attempt\(\s*\$\w+\s*,/' => '->attempt($credenciales, …) con segundo argumento',
    ];

    /**
     * @param  list<string>|null  $directorios
     */
    public function __construct(private readonly ?array $directorios = null) {}

    public function registrar(): void
    {
        $candado = $this;

        it('ningún código del sistema vuelve a pedir la cookie de recordar', function () use ($candado): void {
            $candado->ningunCodigoDelSistemaVuelveAPedirLaCookieDeRecordar();
        });
    }

    public function ningunCodigoDelSistemaVuelveAPedirLaCookieDeRecordar(): void
    {
        $hallazgos = [];

        foreach ($this->directorios() as $directorio) {
            foreach (Finder::create()->files()->in($directorio)->name('*.php')->sortByName() as $archivo) {
                $contenido = $archivo->getContents();

                foreach (self::PATRONES as $patron => $descripcion) {
                    if (preg_match($patron, $contenido) === 1) {
                        $hallazgos[] = $this->nombreLegible($archivo).' → '.$descripcion;
                    }
                }
            }
        }

        Assert::assertSame([], $hallazgos, "Vuelve a emitirse la cookie de recordar en:\n".implode("\n", $hallazgos));
    }

    /**
     * @return list<string>
     */
    private function directorios(): array
    {
        $directorios = $this->directorios ?? [$this->ruta('app'), $this->ruta('routes')];

        foreach ($directorios as $directorio) {
            if (! is_dir($directorio)) {
                Assert::fail("no existe el directorio «{$directorio}» que había que revisar");
            }
        }

        return $directorios;
    }

    /**
     * La ruta relativa a la raíz del proyecto cuando el archivo está dentro,
     * para que el fallo nombre `app/Http/…` y no una ruta absoluta.
     */
    private function nombreLegible(SplFileInfo $archivo): string
    {
        $raiz = rtrim($this->raiz(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        $ruta = $archivo->getPathname();

        return str_starts_with($ruta, $raiz) ? substr($ruta, strlen($raiz)) : $ruta;
    }
}
