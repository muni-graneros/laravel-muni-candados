/**
 * Fixture: código propio de una PWA de terreno, con test dedicado, al que
 * solo le falta la línea de `serviceWorker.register()`. Representa el caso
 * real de seguridad-graneros — GPS, cola offline, botón de pánico — que
 * este candado marcaba en rojo como si fuera el sobrante del scaffold.
 */
const CACHE_NAME = 'terreno-fixture-v1';

self.addEventListener('fetch', (event) => {
    // No cachea nada de la API a propósito: un dato viejo desinforma.
});
