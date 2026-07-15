<p align="center">
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="docs/charte/logo-typo-wordmark.svg">
    <img alt="FuturMeal" src="docs/charte/logo-typo-wordmark-dark.svg" width="320">
  </picture>
</p>

# FuturMeal

Planification de repas collaborative et suivi nutritionnel sport (priorité perte de graisse).

**Code source :** [github.com/Shayajs/futurmeal](https://github.com/Shayajs/futurmeal)

## Stack

- Laravel 13 · Livewire 3 · Breeze · Tailwind · Chart.js
- MySQL 8.4 · Docker (dev + prod)

## Démarrage dev

```bash
# Depuis la racine prog
./dev-up.sh futurmeal

# Dans le conteneur
docker compose -f futurmeal/docker-compose-shaya.dev.yaml exec php composer install
docker compose -f futurmeal/docker-compose-shaya.dev.yaml exec php php artisan futurmeal:import-ciqual --seed-demo
docker compose -f futurmeal/docker-compose-shaya.dev.yaml exec php php artisan migrate

# Front (sur l'hôte)
cd futurmeal && npm install && npm run dev
```

Accès : **http://futurmeal.test**

## Production

FuturMeal tourne sur le **réseau Docker Allotata** `www_laravel_net` (NPM). Seul le conteneur `futurmeal_nginx` y est connecté ; app, queue, scheduler et MariaDB restent sur le réseau interne.

```bash
cp docker/prod/env.prod.example .env
# Renseigner APP_URL, DB_PASSWORD, APP_KEY, etc.

docker compose -f docker-compose.yaml build app
docker compose -f docker-compose.yaml up -d
docker compose -f docker-compose.yaml exec app php artisan livewire:publish --assets
npm ci && npm run build
docker compose -f docker-compose.yaml exec -T nginx nginx -s reload
```

### Nginx Proxy Manager (Allotata)

1. Vérifier que le réseau existe : `docker network inspect www_laravel_net`
2. Dans NPM (port 81) → **Proxy Hosts** → Add :
   - **Domain** : `futurmeal.fr` (ou ton FQDN)
   - **Forward Hostname** : `futurmeal_nginx`
   - **Forward Port** : `80`
   - SSL : Let's Encrypt via NPM

Les conteneurs `app`, `queue`, `scheduler` et `db` ne sont **pas** sur le réseau NPM (isolation).

## Documentation

- [Cahier des charges](docs/CAHIER_DES_CHARGES.md)
- [APIs](docs/APIS.md)
- [Questions ouvertes](docs/QUESTIONS.md)
- [Charte graphique](docs/CHARTE_GRAPHIQUE.md)
- [Visuels charte (10 planches)](docs/charte/)

## Commandes utiles

```bash
php artisan futurmeal:import-ciqual --seed-demo
php artisan futurmeal:import-ciqual --path=/chemin/vers/ciqual.xml
```
