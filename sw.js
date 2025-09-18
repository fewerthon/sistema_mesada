// sw.js - Versão 3, Network-Only (sem cache)

// Vamos atualizar a versão para acionar o ciclo de vida de 'activate'
const CACHE_NAME = 'mesada-cache-v3'; 

// Evento de Instalação: Não fazemos nada aqui, pois não vamos cachear arquivos.
self.addEventListener('install', event => {
  console.log('Service Worker (v3 - Network-Only) instalado.');
  // Força o novo Service Worker a se tornar ativo imediatamente.
  self.skipWaiting();
});

// Evento de Ativação: A única coisa que fazemos é limpar os caches antigos.
self.addEventListener('activate', event => {
  console.log('Service Worker (v3 - Network-Only) ativado.');
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(cacheName => {
          // Deleta TODOS os caches do nosso app, já que não usamos mais.
          console.log('Limpando cache antigo:', cacheName);
          return caches.delete(cacheName);
        })
      );
    })
  );
});

// Evento Fetch: A estratégia é sempre ir para a rede.
self.addEventListener('fetch', event => {
  // Isso satisfaz o requisito do PWA de ter um handler de fetch,
  // mas simplesmente passa a requisição direto para a rede.
  event.respondWith(fetch(event.request));
});