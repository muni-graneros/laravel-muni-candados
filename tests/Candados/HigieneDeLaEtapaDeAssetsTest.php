<?php

declare(strict_types=1);

use Muni\Candados\Candados\HigieneDeLaEtapaDeAssets;
use PHPUnit\Framework\AssertionFailedError;

$fixtures = dirname(__DIR__).'/Fixtures';

$cumple = new HigieneDeLaEtapaDeAssets(
    dockerfile: $fixtures.'/Cumple/Dockerfile.assets',
    packageJson: $fixtures.'/Cumple/package-assets.json',
    packageLock: $fixtures.'/Cumple/package-lock-assets.json',
);

$noCumple = new HigieneDeLaEtapaDeAssets(
    dockerfile: $fixtures.'/NoCumple/Dockerfile.assets',
    packageJson: $fixtures.'/NoCumple/package-assets.json',
    packageLock: $fixtures.'/NoCumple/package-lock-assets.json',
);

it('pasa con la etapa que instala sin devDependencies y sin postinstall', function () use ($cumple): void {
    $cumple->laEtapaNoInstalaDeMas();
});

it('detecta el «npm ci» a secas, que mete las devDependencies en la imagen', function () use ($noCumple): void {
    expect(fn () => $noCumple->laEtapaNoInstalaDeMas())
        ->toThrow(AssertionFailedError::class, '--omit=dev');
});

it('nombra el paquete de escritorio en el mensaje, para que se entienda qué entra', function () use ($noCumple): void {
    // Un mensaje que solo dice «falta el flag» no explica por qué importa. El
    // que lee el rojo tiene que saber QUÉ se está instalando de más.
    try {
        $noCumple->laEtapaNoInstalaDeMas();
        expect(false)->toBeTrue('el candado no detectó el npm ci a secas');
    } catch (AssertionFailedError $error) {
        expect(str_contains($error->getMessage(), 'demo-engine'))->toBeTrue(
            'el mensaje no nombra el paquete que entra de más: '.$error->getMessage()
        );
    }
});

it('pasa cuando la cadena de build está en dependencies, incluidos los plugins del vite.config', function () use ($cumple): void {
    // La fixture que cumple declara «vite» y «laravel-vite-plugin» a mano, y
    // además «@tailwindcss/vite» y «motion» solo aparecen como import en
    // vite.config.js y en resources/js: el candado los deriva de ahí.
    $cumple->loQueNecesitaElBundleEstaEnDependencies();
});

it('detecta vite en devDependencies: la imagen se construiría sin assets', function () use ($noCumple): void {
    expect(fn () => $noCumple->loQueNecesitaElBundleEstaEnDependencies())
        ->toThrow(AssertionFailedError::class, 'vite');
});

it('detecta el paquete de escritorio metido en dependencies, donde --omit=dev no lo saca', function () use ($fixtures): void {
    // El caso más caro: el sistema pone el flag, cree que está cubierto, y el
    // paquete sigue entrando porque está del lado de producción.
    $candado = new HigieneDeLaEtapaDeAssets(
        dockerfile: $fixtures.'/Cumple/Dockerfile.assets',
        packageJson: $fixtures.'/NoCumple/package-assets.json',
        packageLock: $fixtures.'/NoCumple/package-lock-assets.json',
        herramientas: [],
    );

    expect(fn () => $candado->loQueNecesitaElBundleEstaEnDependencies())
        ->toThrow(AssertionFailedError::class, '--omit=dev NO lo excluye');
});

it('pasa cuando lo que se instala en producción sale del registro de npm', function () use ($cumple): void {
    $cumple->produccionSoloHablaConElRegistroDeNpm();
});

it('detecta el tarball de GitHub que sí entra a la imagen de producción', function () use ($noCumple): void {
    expect(fn () => $noCumple->produccionSoloHablaConElRegistroDeNpm())
        ->toThrow(AssertionFailedError::class, 'github.com');
});

it('no se queja del tarball de GitHub que el lock marca como dev', function () use ($cumple): void {
    // La fixture que cumple tiene demo-engine resuelto desde github.com, pero
    // con «dev: true»: --omit=dev lo deja fuera, así que no es un hallazgo.
    $cumple->produccionSoloHablaConElRegistroDeNpm();
});

it('falla claro si el Dockerfile ya no tiene la etapa que este candado mira', function () use ($fixtures): void {
    $candado = new HigieneDeLaEtapaDeAssets(dockerfile: $fixtures.'/Cumple/Dockerfile');

    expect(fn () => $candado->laEtapaNoInstalaDeMas())
        ->toThrow(AssertionFailedError::class, 'ya no tiene una etapa');
});

it('falla claro si no existe el archivo', function () use ($fixtures): void {
    $candado = new HigieneDeLaEtapaDeAssets(dockerfile: $fixtures.'/NoExiste/Dockerfile');

    expect(fn () => $candado->laEtapaNoInstalaDeMas())
        ->toThrow(AssertionFailedError::class, 'no existe el Dockerfile');
});
