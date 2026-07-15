# FuturMeal

Planification de repas collaborative et suivi nutritionnel sport (priorité perte de graisse).

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

```bash
cp docker/prod/env.prod.example .env
docker compose -f docker-compose.yaml build app
docker compose -f docker-compose.yaml up -d
```

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
