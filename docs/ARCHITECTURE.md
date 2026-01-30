YkClean/
├── backend/                    # Symfony
│   ├── bin/
│   ├── config/
│   ├── migrations/
│   ├── public/
│   ├── src/
│   │   ├── Controller/
│   │   ├── Entity/
│   │   ├── Repository/
│   │   ├── Service/
│   │   └── Security/
│   ├── var/
│   ├── vendor/
│   ├── .env
│   ├── composer.json
│   └── symfony.lock
│
├── mobile/                     # React Native
│   ├── src/
│   │   ├── screens/
│   │   ├── components/
│   │   ├── services/
│   │   ├── navigation/
│   │   ├── store/            # Redux/Context
│   │   └── utils/
│   ├── android/
│   ├── ios/
│   ├── App.tsx
│   ├── package.json
│   └── tsconfig.json
│
├── shared/                     # Types/constantes partagés
│   └── types/
│       └── api-types.ts
│
└── docs/
    ├── ARCHITECTURE.md
    └── API.md

    # Architecture du Projet

## Stack Simplifié

### Backend
- **Framework**: Symfony 6.4/7.x
- **API**: API Platform (REST + JSON-LD)
- **Base de données**: PostgreSQL (local)
- **Authentification**: JWT
- **ORM**: Doctrine
- **URL dev**: http://localhost:8000

### Mobile
- **Framework**: React Native + TypeScript
- **Navigation**: React Navigation
- **API**: Axios
- **Storage**: AsyncStorage
- **Metro**: http://localhost:8081

## Services Requis

### PostgreSQL
- Host: 127.0.0.1
- Port: 5432
- Database: mon_projet_db
- User: postgres

## Commandes

### Backend
symfony server:start                    # Démarrer le serveur
php bin/console doctrine:migrations:migrate   # Migrations
php bin/console make:entity            # Créer une entité
php bin/console cache:clear            # Vider le cache

### Mobile
npm start                              # Démarrer Metro
npm run android                        # Lancer sur Android
npm run ios                           # Lancer sur iOS

## URLs Importantes
- API: http://localhost:8000/api
- API Docs: http://localhost:8000/api/docs
- Login: POST http://localhost:8000/api/login_check