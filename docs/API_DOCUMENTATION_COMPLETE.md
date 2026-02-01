# Documentation API REST - Plateforme de Services à Domicile

## 📋 Vue d'ensemble

Cette documentation décrit **tous les endpoints API** de la plateforme permettant le développement **indépendant** du frontend web et mobile.

**Base URL**: `http://localhost:8000/api`  
**Format**: JSON  
**Authentification**: JWT (JSON Web Token)

---

## 📑 Table des Matières

1. [Authentification](#1-authentification)
2. [Catégories et Sous-catégories](#2-catégories-et-sous-catégories)
3. [Client](#3-client)
4. [Prestataire](#4-prestataire)
5. [Admin](#5-admin)
6. [Common](#6-common-endpoints-communs)
7. [Module Financial](#7-module-financial)
8. [Codes de Statut HTTP](#8-codes-de-statut-http)
9. [Modèles de Données](#9-modèles-de-données)

---

## 1. Authentification

### 1.1 Inscription

```http
POST /api/register
```

**Body (Client)**:
```json
{
  "email": "client@example.com",
  "password": "SecurePassword123!",
  "firstName": "Jean",
  "lastName": "Dupont",
  "phone": "0612345678",
  "userType": "client"
}
```

**Body (Prestataire)**:
```json
{
  "email": "prestataire@example.com",
  "password": "SecurePassword123!",
  "firstName": "Marie",
  "lastName": "Martin",
  "phone": "0698765432",
  "userType": "prestataire",
  "siret": "12345678901234",
  "hourlyRate": 25.00,
  "radius": 15,
  "specializations": [
    {
      "subcategoryId": 2,
      "hourlyRate": 27.00
    },
    {
      "subcategoryId": 3,
      "hourlyRate": 32.00
    }
  ]
}
```

**Response** (201 Created):
```json
{
  "success": true,
  "message": "Inscription réussie. Veuillez vérifier votre email.",
  "data": {
    "id": 1,
    "email": "client@example.com",
    "firstName": "Jean",
    "lastName": "Dupont",
    "userType": "client",
    "isVerified": false
  }
}
```

---

### 1.2 Connexion

```http
POST /api/login
```

**Body**:
```json
{
  "email": "client@example.com",
  "password": "SecurePassword123!"
}
```

**Response** (200 OK):
```json
{
  "success": true,
  "data": {
    "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "refreshToken": "def502003d7c8b...",
    "user": {
      "id": 1,
      "email": "client@example.com",
      "firstName": "Jean",
      "lastName": "Dupont",
      "roles": ["ROLE_CLIENT"],
      "userType": "client"
    }
  }
}
```

---

### 1.3 Rafraîchir le Token

```http
POST /api/refresh-token
```

**Body**:
```json
{
  "refreshToken": "def502003d7c8b..."
}
```

**Response** (200 OK):
```json
{
  "success": true,
  "data": {
    "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "refreshToken": "def502003d7c8b..."
  }
}
```

---

### 1.4 Mot de Passe Oublié

```http
POST /api/forgot-password
```

**Body**:
```json
{
  "email": "client@example.com"
}
```

**Response** (200 OK):
```json
{
  "success": true,
  "message": "Email de réinitialisation envoyé"
}
```

---

### 1.5 Réinitialiser le Mot de Passe

```http
POST /api/reset-password
```

**Body**:
```json
{
  "token": "abc123def456...",
  "password": "NewSecurePassword123!"
}
```

**Response** (200 OK):
```json
{
  "success": true,
  "message": "Mot de passe réinitialisé avec succès"
}
```

---

## 2. Catégories et Sous-catégories

### 2.1 Liste des Catégories Racines

```http
GET /api/categories
```

**Description**: Retourne toutes les catégories de niveau racine (sans parent)

**Response** (200 OK):
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Nettoyage",
      "slug": "nettoyage",
      "description": "Services de nettoyage professionnel",
      "icon": "cleaning-icon.svg",
      "displayOrder": 1,
      "childrenCount": 3,
      "subcategoriesCount": 8,
      "isActive": true
    },
    {
      "id": 2,
      "name": "Repassage",
      "slug": "repassage",
      "description": "Services de repassage et pressing",
      "icon": "ironing-icon.svg",
      "displayOrder": 2,
      "childrenCount": 0,
      "subcategoriesCount": 2,
      "isActive": true
    },
    {
      "id": 3,
      "name": "Jardinage",
      "slug": "jardinage",
      "description": "Entretien d'espaces verts",
      "icon": "garden-icon.svg",
      "displayOrder": 3,
      "childrenCount": 2,
      "subcategoriesCount": 6,
      "isActive": true
    }
  ]
}
```

---

### 2.2 Arborescence Complète

```http
GET /api/categories/tree
```

**Description**: Retourne l'arborescence complète des catégories avec leurs enfants et sous-catégories

**Response** (200 OK):
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Nettoyage",
      "slug": "nettoyage",
      "icon": "cleaning-icon.svg",
      "level": 0,
      "children": [
        {
          "id": 4,
          "name": "Entretien courant",
          "slug": "entretien-courant",
          "parent_id": 1,
          "level": 1,
          "subcategories": [
            {
              "id": 1,
              "name": "Nettoyage léger",
              "slug": "nettoyage-leger",
              "description": "Nettoyage rapide et entretien quotidien",
              "basePrice": 20.00,
              "estimatedDuration": 120,
              "icon": "light-cleaning.svg",
              "requirements": ["aspirateur", "produits basiques"]
            },
            {
              "id": 2,
              "name": "Nettoyage standard",
              "slug": "nettoyage-standard",
              "description": "Nettoyage complet des pièces",
              "basePrice": 25.00,
              "estimatedDuration": 180,
              "icon": "standard-cleaning.svg",
              "requirements": ["aspirateur", "serpillière", "produits"]
            }
          ]
        },
        {
          "id": 5,
          "name": "Grand ménage",
          "slug": "grand-menage",
          "parent_id": 1,
          "level": 1,
          "subcategories": [
            {
              "id": 3,
              "name": "Grand ménage classique",
              "slug": "grand-menage-classique",
              "basePrice": 30.00,
              "estimatedDuration": 300,
              "requirements": ["matériel complet"]
            },
            {
              "id": 4,
              "name": "Grand ménage avec vitres",
              "slug": "grand-menage-vitres",
              "basePrice": 35.00,
              "estimatedDuration": 360,
              "requirements": ["matériel complet", "kit vitres"]
            }
          ]
        },
        {
          "id": 6,
          "name": "Nettoyage spécialisé",
          "slug": "nettoyage-specialise",
          "parent_id": 1,
          "level": 1,
          "subcategories": [
            {
              "id": 5,
              "name": "Nettoyage après travaux",
              "slug": "nettoyage-apres-travaux",
              "basePrice": 40.00,
              "estimatedDuration": 480,
              "requirements": ["matériel professionnel"]
            },
            {
              "id": 6,
              "name": "Nettoyage de fin de bail",
              "slug": "nettoyage-fin-bail",
              "basePrice": 45.00,
              "estimatedDuration": 400,
              "requirements": ["matériel professionnel", "garantie"]
            }
          ]
        }
      ]
    }
  ]
}
```

---

### 2.3 Détails d'une Catégorie

```http
GET /api/categories/{id}
```

**Response** (200 OK):
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "Nettoyage",
    "slug": "nettoyage",
    "description": "Services de nettoyage professionnel à domicile",
    "icon": "cleaning-icon.svg",
    "parent": null,
    "level": 0,
    "displayOrder": 1,
    "isActive": true,
    "children": [
      {
        "id": 4,
        "name": "Entretien courant",
        "slug": "entretien-courant"
      },
      {
        "id": 5,
        "name": "Grand ménage",
        "slug": "grand-menage"
      }
    ],
    "subcategoriesCount": 8,
    "createdAt": "2024-01-10T10:00:00+00:00"
  }
}
```

---

### 2.4 Catégories Enfants

```http
GET /api/categories/{id}/children
```

**Description**: Retourne les catégories enfants directes d'une catégorie

**Response** (200 OK):
```json
{
  "success": true,
  "data": [
    {
      "id": 4,
      "name": "Entretien courant",
      "slug": "entretien-courant",
      "description": "Nettoyage régulier et entretien",
      "parent_id": 1,
      "level": 1,
      "subcategoriesCount": 2,
      "displayOrder": 1
    },
    {
      "id": 5,
      "name": "Grand ménage",
      "slug": "grand-menage",
      "description": "Nettoyage en profondeur",
      "parent_id": 1,
      "level": 1,
      "subcategoriesCount": 2,
      "displayOrder": 2
    }
  ]
}
```

---

### 2.5 Sous-catégories d'une Catégorie

```http
GET /api/categories/{id}/subcategories
```

**Description**: Retourne toutes les sous-catégories d'une catégorie (récursif)

**Response** (200 OK):
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "category_id": 4,
      "categoryName": "Entretien courant",
      "name": "Nettoyage léger",
      "slug": "nettoyage-leger",
      "description": "Nettoyage rapide et entretien quotidien",
      "basePrice": 20.00,
      "estimatedDuration": 120,
      "icon": "light-cleaning.svg",
      "requirements": ["aspirateur", "produits basiques"],
      "displayOrder": 1,
      "isActive": true
    },
    {
      "id": 2,
      "category_id": 4,
      "categoryName": "Entretien courant",
      "name": "Nettoyage standard",
      "slug": "nettoyage-standard",
      "description": "Nettoyage complet des pièces principales",
      "basePrice": 25.00,
      "estimatedDuration": 180,
      "icon": "standard-cleaning.svg",
      "requirements": ["aspirateur", "serpillière", "produits ménagers"],
      "displayOrder": 2,
      "isActive": true
    }
  ]
}
```

---

### 2.6 Détails d'une Sous-catégorie

```http
GET /api/subcategories/{id}
```

**Response** (200 OK):
```json
{
  "success": true,
  "data": {
    "id": 3,
    "category": {
      "id": 5,
      "name": "Grand ménage",
      "slug": "grand-menage"
    },
    "name": "Grand ménage classique",
    "slug": "grand-menage-classique",
    "description": "Nettoyage en profondeur de toutes les pièces",
    "basePrice": 30.00,
    "estimatedDuration": 300,
    "icon": "deep-cleaning.svg",
    "requirements": [
      "matériel complet",
      "aspirateur professionnel",
      "produits spécialisés"
    ],
    "displayOrder": 1,
    "isActive": true,
    "prestataireCount": 45,
    "averagePrice": 32.50,
    "createdAt": "2024-01-10T10:00:00+00:00"
  }
}
```

---

### 2.7 Tarification d'une Sous-catégorie

```http
GET /api/subcategories/{id}/pricing
```

**Description**: Retourne les statistiques de tarification pour une sous-catégorie

**Response** (200 OK):
```json
{
  "success": true,
  "data": {
    "subcategory": {
      "id": 3,
      "name": "Grand ménage classique",
      "basePrice": 30.00
    },
    "statistics": {
      "averagePrice": 32.50,
      "minPrice": 28.00,
      "maxPrice": 40.00,
      "medianPrice": 32.00,
      "prestataireCount": 45,
      "averageDuration": 310
    },
    "priceRanges": [
      {
        "range": "28-30€",
        "count": 15,
        "percentage": 33.3
      },
      {
        "range": "30-35€",
        "count": 20,
        "percentage": 44.4
      },
      {
        "range": "35-40€",
        "count": 10,
        "percentage": 22.3
      }
    ]
  }
}
```

---

### 2.8 Chemin d'une Catégorie (Breadcrumb)

```http
GET /api/categories/{id}/path
```

**Description**: Retourne le chemin complet depuis la racine

**Response** (200 OK):
```json
{
  "success": true,
  "data": {
    "path": [
      {
        "id": 1,
        "name": "Nettoyage",
        "slug": "nettoyage",
        "level": 0
      },
      {
        "id": 5,
        "name": "Grand ménage",
        "slug": "grand-menage",
        "level": 1
      }
    ]
  }
}
```

---

## 3. Client

**Headers requis pour toutes les routes Client**:
```
Authorization: Bearer {token}
```

### 2.1 Profil

#### Récupérer le profil

```http
GET /api/client/profile
```

**Response** (200 OK):
```json
{
  "success": true,
  "data": {
    "id": 1,
    "email": "client@example.com",
    "firstName": "Jean",
    "lastName": "Dupont",
    "phone": "0612345678",
    "address": "123 Rue Example",
    "city": "Paris",
    "postalCode": "75001",
    "preferredPaymentMethod": "card",
    "createdAt": "2024-01-15T10:30:00+00:00",
    "isVerified": true
  }
}
```

---

#### Mettre à jour le profil

```http
PUT /api/client/profile
```

**Body**:
```json
{
  "firstName": "Jean",
  "lastName": "Dupont",
  "phone": "0612345678",
  "address": "123 Rue Example",
  "city": "Paris",
  "postalCode": "75001"
}
```

**Response** (200 OK):
```json
{
  "success": true,
  "message": "Profil mis à jour avec succès",
  "data": {
    "id": 1,
    "email": "client@example.com",
    "firstName": "Jean",
    "lastName": "Dupont",
    "phone": "0612345678"
  }
}
```

---

### 2.2 Demandes de Service

#### Créer une demande de service

```http
POST /api/client/service-requests
```

**Body**:
```json
{
  "categoryId": 1,
  "subcategoryId": 3,
  "title": "Grand ménage appartement 80m²",
  "description": "Besoin d'un grand ménage complet de mon appartement. Cuisine, salle de bain, 2 chambres, salon.",
  "address": "123 Rue Example, 75001 Paris",
  "city": "Paris",
  "postalCode": "75001",
  "latitude": 48.8566,
  "longitude": 2.3522,
  "preferredDate": "2024-02-15T14:00:00+00:00",
  "alternativeDates": [
    "2024-02-16T10:00:00+00:00",
    "2024-02-17T15:00:00+00:00"
  ],
  "estimatedDuration": 300,
  "frequency": "once",
  "budget": 150.00,
  "surfaceArea": 80,
  "specificRequirements": ["fenêtres", "four", "réfrigérateur"]
}
```

**Champs** :
- `categoryId` (required): ID de la catégorie principale
- `subcategoryId` (required): ID de la sous-catégorie spécifique
- `surfaceArea` (optional): Surface en m² (pour nettoyage)
- `specificRequirements` (optional): Détails spécifiques (tableau)
- `frequency` (required): `once`, `weekly`, `biweekly`, `monthly`

**Response** (201 Created):
```json
{
  "success": true,
  "message": "Demande de service créée avec succès",
  "data": {
    "id": 1,
    "category": {
      "id": 1,
      "name": "Nettoyage"
    },
    "subcategory": {
      "id": 3,
      "name": "Grand ménage classique",
      "basePrice": 30.00,
      "estimatedDuration": 300
    },
    "title": "Grand ménage appartement 80m²",
    "description": "Besoin d'un grand ménage complet...",
    "address": "123 Rue Example, 75001 Paris",
    "surfaceArea": 80,
    "specificRequirements": ["fenêtres", "four", "réfrigérateur"],
    "preferredDate": "2024-02-15T14:00:00+00:00",
    "estimatedPrice": {
      "min": 120.00,
      "max": 180.00,
      "recommended": 150.00
    },
    "matchingPrestataires": 12,
    "status": "open",
    "createdAt": "2024-01-20T10:30:00+00:00",
    "expiresAt": "2024-01-27T10:30:00+00:00"
  }
}
```

---

#### Liste des demandes de service

```http
GET /api/client/service-requests
GET /api/client/service-requests?status=open
GET /api/client/service-requests?page=1&limit=10
```

**Query Parameters**:
- `status` (optional): `open`, `quoted`, `in_progress`, `completed`, `cancelled`
- `page` (optional): Numéro de page (défaut: 1)
- `limit` (optional): Nombre par page (défaut: 10, max: 50)

**Response** (200 OK):
```json
{
  "success": true,
  "data": {
    "items": [
      {
        "id": 1,
        "categoryId": 1,
        "categoryName": "Nettoyage",
        "title": "Nettoyage appartement 3 pièces",
        "status": "open",
        "budget": 80.00,
        "quotesCount": 3,
        "createdAt": "2024-01-20T10:30:00+00:00"
      }
    ],
    "pagination": {
      "total": 15,
      "page": 1,
      "limit": 10,
      "pages": 2
    }
  }
}
```

---

#### Détails d'une demande

```http
GET /api/client/service-requests/{id}
```

**Response** (200 OK):
```json
{
  "success": true,
  "data": {
    "id": 1,
    "category": {
      "id": 1,
      "name": "Nettoyage",
      "slug": "nettoyage"
    },
    "title": "Nettoyage appartement 3 pièces",
    "description": "Besoin d'un nettoyage complet...",
    "address": "123 Rue Example, 75001 Paris",
    "preferredDate": "2024-02-15T14:00:00+00:00",
    "duration": 180,
    "budget": 80.00,
    "status": "open",
    "quotes": [
      {
        "id": 1,
        "prestataire": {
          "id": 5,
          "firstName": "Marie",
          "lastName": "Martin",
          "averageRating": 4.8
        },
        "amount": 75.00,
        "proposedDate": "2024-02-15T14:00:00+00:00",
        "status": "pending"
      }
    ],
    "createdAt": "2024-01-20T10:30:00+00:00"
  }
}
```

---

### 2.3 Devis

#### Liste des devis reçus

```http
GET /api/client/service-requests/{id}/quotes
```

**Response** (200 OK):
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "serviceRequestId": 1,
      "prestataire": {
        "id": 5,
        "firstName": "Marie",
        "lastName": "Martin",
        "averageRating": 4.8,
        "totalReviews": 45,
        "experienceYears": 5
      },
      "amount": 75.00,
      "proposedDate": "2024-02-15T14:00:00+00:00",
      "proposedDuration": 180,
      "description": "Je propose un nettoyage complet...",
      "status": "pending",
      "validUntil": "2024-01-25T10:30:00+00:00",
      "createdAt": "2024-01-20T14:00:00+00:00"
    }
  ]
}
```

---

#### Accepter un devis

```http
POST /api/client/quotes/{id}/accept
```

**Response** (200 OK):
```json
{
  "success": true,
  "message": "Devis accepté. Réservation créée.",
  "data": {
    "quote": {
      "id": 1,
      "status": "accepted",
      "acceptedAt": "2024-01-20T15:30:00+00:00"
    },
    "booking": {
      "id": 1,
      "scheduledDate": "2024-02-15",
      "scheduledTime": "14:00:00",
      "amount": 75.00,
      "status": "scheduled"
    }
  }
}
```

---

#### Rejeter un devis

```http
POST /api/client/quotes/{id}/reject
```

**Body**:
```json
{
  "reason": "Tarif trop élevé"
}
```

**Response** (200 OK):
```json
{
  "success": true,
  "message": "Devis rejeté",
  "data": {
    "id": 1,
    "status": "rejected",
    "rejectedAt": "2024-01-20T15:30:00+00:00"
  }
}
```

---

### 2.4 Réservations

#### Liste des réservations

```http
GET /api/client/bookings
GET /api/client/bookings?status=scheduled
GET /api/client/bookings?page=1&limit=10
```

**Query Parameters**:
- `status` (optional): `scheduled`, `confirmed`, `in_progress`, `completed`, `cancelled`
- `page`, `limit`: Pagination

**Response** (200 OK):
```json
{
  "success": true,
  "data": {
    "items": [
      {
        "id": 1,
        "prestataire": {
          "id": 5,
          "firstName": "Marie",
          "lastName": "Martin",
          "phone": "0698765432"
        },
        "serviceCategory": {
          "id": 1,
          "name": "Nettoyage"
        },
        "scheduledDate": "2024-02-15",
        "scheduledTime": "14:00:00",
        "duration": 180,
        "amount": 75.00,
        "status": "scheduled",
        "address": "123 Rue Example, 75001 Paris"
      }
    ],
    "pagination": {
      "total": 8,
      "page": 1,
      "limit": 10,
      "pages": 1
    }
  }
}
```

---

#### Détails d'une réservation

```http
GET /api/client/bookings/{id}
```

**Response** (200 OK):
```json
{
  "success": true,
  "data": {
    "id": 1,
    "quote": {
      "id": 1,
      "description": "Nettoyage complet..."
    },
    "prestataire": {
      "id": 5,
      "firstName": "Marie",
      "lastName": "Martin",
      "phone": "0698765432",
      "email": "marie.martin@example.com",
      "averageRating": 4.8
    },
    "serviceCategory": {
      "id": 1,
      "name": "Nettoyage"
    },
    "scheduledDate": "2024-02-15",
    "scheduledTime": "14:00:00",
    "duration": 180,
    "address": "123 Rue Example, 75001 Paris",
    "amount": 75.00,
    "status": "scheduled",
    "payment": {
      "id": 1,
      "status": "completed",
      "paidAt": "2024-01-20T16:00:00+00:00"
    },
    "createdAt": "2024-01-20T15:30:00+00:00"
  }
}
```

---

#### Annuler une réservation

```http
POST /api/client/bookings/{id}/cancel
```

**Body**:
```json
{
  "reason": "Empêchement de dernière minute"
}
```

**Response** (200 OK):
```json
{
  "success": true,
  "message": "Réservation annulée",
  "data": {
    "id": 1,
    "status": "cancelled",
    "cancelledAt": "2024-01-21T10:00:00+00:00",
    "cancellationReason": "Empêchement de dernière minute"
  }
}
```

---

### 2.5 Avis

#### Créer un avis

```http
POST /api/client/reviews
```

**Body**:
```json
{
  "bookingId": 1,
  "rating": 5,
  "comment": "Excellent service, très professionnel !",
  "qualityRating": 5,
  "punctualityRating": 5,
  "professionalismRating": 5
}
```

**Response** (201 Created):
```json
{
  "success": true,
  "message": "Avis créé avec succès",
  "data": {
    "id": 1,
    "bookingId": 1,
    "prestataireId": 5,
    "rating": 5,
    "comment": "Excellent service...",
    "createdAt": "2024-02-16T10:00:00+00:00"
  }
}
```

---

## 3. Prestataire

**Headers requis**:
```
Authorization: Bearer {token}
```

### 3.1 Profil

#### Récupérer le profil

```http
GET /api/prestataire/profile
```

**Response** (200 OK):
```json
{
  "success": true,
  "data": {
    "id": 5,
    "email": "prestataire@example.com",
    "firstName": "Marie",
    "lastName": "Martin",
    "phone": "0698765432",
    "siret": "12345678901234",
    "hourlyRate": 25.00,
    "radius": 15,
    "averageRating": 4.8,
    "totalReviews": 45,
    "experienceYears": 5,
    "bio": "Professionnelle du nettoyage...",
    "specializations": [
      {
        "id": 1,
        "subcategory": {
          "id": 2,
          "name": "Nettoyage standard",
          "category": {
            "id": 4,
            "name": "Entretien courant"
          }
        },
        "hourlyRate": 27.00
      },
      {
        "id": 2,
        "subcategory": {
          "id": 3,
          "name": "Grand ménage classique",
          "category": {
            "id": 5,
            "name": "Grand ménage"
          }
        },
        "hourlyRate": 32.00
      }
    ],
    "isApproved": true,
    "approvedAt": "2024-01-10T12:00:00+00:00"
  }
}
```

---

#### Mettre à jour le profil

```http
PUT /api/prestataire/profile
```

**Body**:
```json
{
  "firstName": "Marie",
  "lastName": "Martin",
  "phone": "0698765432",
  "hourlyRate": 28.00,
  "radius": 20,
  "bio": "Professionnelle du nettoyage...",
  "experienceYears": 6
}
```

**Response** (200 OK):
```json
{
  "success": true,
  "message": "Profil mis à jour avec succès",
  "data": {
    "id": 5,
    "hourlyRate": 28.00,
    "radius": 20
  }
}
```

---

### 3.2 Spécialisations

#### Liste des spécialisations

```http
GET /api/prestataire/specializations
```

**Description**: Retourne toutes les spécialisations (sous-catégories) du prestataire avec leurs tarifs personnalisés

**Response** (200 OK):
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "subcategory": {
        "id": 2,
        "name": "Nettoyage standard",
        "slug": "nettoyage-standard",
        "category": {
          "id": 4,
          "name": "Entretien courant"
        },
        "basePrice": 25.00,
        "estimatedDuration": 180
      },
      "hourlyRate": 27.00,
      "experience": "3 ans d'expérience dans le nettoyage résidentiel",
      "isActive": true,
      "addedAt": "2024-01-10T12:00:00+00:00"
    },
    {
      "id": 2,
      "subcategory": {
        "id": 3,
        "name": "Grand ménage classique",
        "slug": "grand-menage-classique",
        "category": {
          "id": 5,
          "name": "Grand ménage"
        },
        "basePrice": 30.00,
        "estimatedDuration": 300
      },
      "hourlyRate": 32.00,
      "experience": "Spécialiste grand ménage - 5 ans",
      "isActive": true,
      "addedAt": "2024-01-10T12:00:00+00:00"
    }
  ]
}
```

---

#### Mettre à jour les spécialisations

```http
PUT /api/prestataire/specializations
```

**Description**: Mise à jour complète des spécialisations du prestataire

**Body**:
```json
{
  "specializations": [
    {
      "subcategoryId": 2,
      "hourlyRate": 27.00,
      "experience": "3 ans d'expérience"
    },
    {
      "subcategoryId": 3,
      "hourlyRate": 32.00,
      "experience": "5 ans - Spécialiste"
    },
    {
      "subcategoryId": 6,
      "hourlyRate": 45.00,
      "experience": "Certifié fin de bail"
    }
  ]
}
```

**Response** (200 OK):
```json
{
  "success": true,
  "message": "Spécialisations mises à jour avec succès",
  "data": {
    "specializationsCount": 3,
    "specializations": [
      {
        "subcategoryId": 2,
        "subcategoryName": "Nettoyage standard",
        "hourlyRate": 27.00
      },
      {
        "subcategoryId": 3,
        "subcategoryName": "Grand ménage classique",
        "hourlyRate": 32.00
      },
      {
        "subcategoryId": 6,
        "subcategoryName": "Nettoyage de fin de bail",
        "hourlyRate": 45.00
      }
    ]
  }
}
```

---

#### Ajouter une spécialisation

```http
POST /api/prestataire/specializations/{subcategoryId}
```

**Body**:
```json
{
  "hourlyRate": 35.00,
  "experience": "2 ans - Nettoyage après travaux"
}
```

**Response** (201 Created):
```json
{
  "success": true,
  "message": "Spécialisation ajoutée avec succès",
  "data": {
    "id": 3,
    "subcategory": {
      "id": 5,
      "name": "Nettoyage après travaux"
    },
    "hourlyRate": 35.00,
    "experience": "2 ans - Nettoyage après travaux",
    "isActive": true
  }
}
```

---

#### Supprimer une spécialisation

```http
DELETE /api/prestataire/specializations/{subcategoryId}
```

**Response** (200 OK):
```json
{
  "success": true,
  "message": "Spécialisation supprimée avec succès"
}
```

---

### 3.3 Disponibilités

#### Liste des disponibilités

```http
GET /api/prestataire/availabilities
```

**Response** (200 OK):
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "dayOfWeek": 1,
      "startTime": "09:00:00",
      "endTime": "17:00:00",
      "isRecurring": true,
      "isAvailable": true
    },
    {
      "id": 2,
      "specificDate": "2024-02-15",
      "startTime": "14:00:00",
      "endTime": "18:00:00",
      "isRecurring": false,
      "isAvailable": false,
      "notes": "Rendez-vous médical"
    }
  ]
}
```

---

#### Créer une disponibilité

```http
POST /api/prestataire/availabilities
```

**Body (Récurrente)**:
```json
{
  "dayOfWeek": 1,
  "startTime": "09:00",
  "endTime": "17:00",
  "isRecurring": true
}
```

**Body (Ponctuelle)**:
```json
{
  "specificDate": "2024-02-15",
  "startTime": "14:00",
  "endTime": "18:00",
  "isRecurring": false,
  "notes": "Disponibilité exceptionnelle"
}
```

**Response** (201 Created):
```json
{
  "success": true,
  "message": "Disponibilité créée avec succès",
  "data": {
    "id": 3,
    "dayOfWeek": 1,
    "startTime": "09:00:00",
    "endTime": "17:00:00",
    "isRecurring": true
  }
}
```

---

#### Modifier une disponibilité

```http
PUT /api/prestataire/availabilities/{id}
```

**Body**:
```json
{
  "startTime": "10:00",
  "endTime": "18:00"
}
```

**Response** (200 OK):
```json
{
  "success": true,
  "message": "Disponibilité mise à jour",
  "data": {
    "id": 1,
    "startTime": "10:00:00",
    "endTime": "18:00:00"
  }
}
```

---

#### Supprimer une disponibilité

```http
DELETE /api/prestataire/availabilities/{id}
```

**Response** (200 OK):
```json
{
  "success": true,
  "message": "Disponibilité supprimée"
}
```

---

### 3.3 Absences

#### Créer une absence

```http
POST /api/prestataire/absences
```

**Body**:
```json
{
  "startDate": "2024-03-01",
  "endDate": "2024-03-15",
  "reason": "Vacances",
  "requiresReplacement": false
}
```

**Response** (201 Created):
```json
{
  "success": true,
  "message": "Absence enregistrée",
  "data": {
    "id": 1,
    "startDate": "2024-03-01",
    "endDate": "2024-03-15",
    "reason": "Vacances",
    "status": "pending",
    "affectedBookings": []
  }
}
```

---

#### Liste des absences

```http
GET /api/prestataire/absences
```

**Response** (200 OK):
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "startDate": "2024-03-01",
      "endDate": "2024-03-15",
      "reason": "Vacances",
      "status": "approved",
      "requiresReplacement": false
    }
  ]
}
```

---

### 3.4 Demandes de Service Disponibles

#### Liste des demandes dans ma zone

```http
GET /api/prestataire/service-requests
GET /api/prestataire/service-requests?categoryId=1
GET /api/prestataire/service-requests?distance=10
```

**Query Parameters**:
- `categoryId` (optional): Filtrer par catégorie
- `distance` (optional): Distance maximale en km
- `minBudget`, `maxBudget` (optional): Fourchette de budget

**Response** (200 OK):
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "client": {
        "firstName": "Jean",
        "averageRating": 4.5
      },
      "category": {
        "id": 1,
        "name": "Nettoyage"
      },
      "title": "Nettoyage appartement 3 pièces",
      "description": "Besoin d'un nettoyage complet...",
      "city": "Paris",
      "postalCode": "75001",
      "distance": 3.5,
      "preferredDate": "2024-02-15T14:00:00+00:00",
      "budget": 80.00,
      "quotesCount": 2,
      "createdAt": "2024-01-20T10:30:00+00:00",
      "expiresAt": "2024-01-27T10:30:00+00:00"
    }
  ]
}
```

---

### 3.5 Devis

#### Créer un devis

```http
POST /api/prestataire/quotes
```

**Body**:
```json
{
    "serviceRequestId": 1,
  "items": [
    {
      "description": "Nettoyage cuisine",
      "quantity": 1,
      "unit": "pièce",
      "unitPrice": 30.00,
      "estimatedDuration": 60
    },
    {
      "description": "Nettoyage salle de bain",
      "quantity": 1,
      "unit": "pièce",
      "unitPrice": 25.00,
      "estimatedDuration": 45
    }
  ]
}
```

**Response** (201 Created):
```json
{
  "success": true,
  "data": {
    "id": 1,
    "amount": 85.00,
    "proposedDuration": 130,
    "items": [
      {
        "id": 1,
        "description": "Nettoyage cuisine",
        "quantity": 1,
        "unit": "pièce",
        "unitPrice": 30.00,
        "totalPrice": 30.00,
        "estimatedDuration": 60,
        "isOptional": false
      },
      {
        "id": 2,
        "description": "Nettoyage salle de bain",
        "quantity": 1,
        "unit": "pièce",
        "unitPrice": 25.00,
        "totalPrice": 25.00,
        "estimatedDuration": 45,
        "isOptional": false
      },
      {
        "id": 3,
        "description": "Nettoyage 3 chambres",
        "quantity": 3,
        "unit": "pièce",
        "unitPrice": 10.00,
        "totalPrice": 30.00,
        "estimatedDuration": 25,
        "isOptional": true
      }
    ]
  }
}
```

---

#### Liste de mes devis

```http
GET /api/prestataire/quotes
GET /api/prestataire/quotes?status=pending
```

**Response** (200 OK):
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "serviceRequest": {
        "id": 1,
        "title": "Nettoyage appartement 3 pièces",
        "client": {
          "firstName": "Jean",
          "lastName": "D."
        }
      },
      "amount": 75.00,
      "status": "pending",
      "validUntil": "2024-01-25T23:59:59+00:00",
      "createdAt": "2024-01-20T14:00:00+00:00"
    }
  ]
}
```

---

### 3.6 Réservations

#### Liste de mes réservations

```http
GET /api/prestataire/bookings
GET /api/prestataire/bookings?status=scheduled
GET /api/prestataire/bookings?date=2024-02-15
```

**Response** (200 OK):
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "client": {
        "id": 1,
        "firstName": "Jean",
        "lastName": "Dupont",
        "phone": "0612345678"
      },
      "serviceCategory": {
        "id": 1,
        "name": "Nettoyage"
      },
      "scheduledDate": "2024-02-15",
      "scheduledTime": "14:00:00",
      "duration": 180,
      "address": "123 Rue Example, 75001 Paris",
      "amount": 75.00,
      "status": "scheduled"
    }
  ]
}
```

---

#### Détails d'une réservation

```http
GET /api/prestataire/bookings/{id}
```

**Response** (200 OK):
```json
{
  "success": true,
  "data": {
    "id": 1,
    "client": {
      "id": 1,
      "firstName": "Jean",
      "lastName": "Dupont",
      "phone": "0612345678",
      "address": "123 Rue Example, 75001 Paris"
    },
    "serviceCategory": {
      "id": 1,
      "name": "Nettoyage"
    },
    "scheduledDate": "2024-02-15",
    "scheduledTime": "14:00:00",
    "duration": 180,
    "amount": 75.00,
    "status": "scheduled",
    "quote": {
      "description": "Nettoyage complet..."
    }
  }
}
```

---

#### Changer le statut d'une réservation

```http
PUT /api/prestataire/bookings/{id}/status
```

**Body**:
```json
{
  "status": "confirmed"
}
```

**Statuts possibles**:
- `confirmed`: Confirmer la réservation
- `in_progress`: Démarrer le service
- `completed`: Terminer le service

**Pour `completed`**:
```json
{
  "status": "completed",
  "completionNotes": "Service réalisé avec succès. Toutes les pièces ont été nettoyées."
}
```

**Response** (200 OK):
```json
{
  "success": true,
  "message": "Statut mis à jour",
  "data": {
    "id": 1,
    "status": "completed",
    "actualStartTime": "2024-02-15T14:05:00+00:00",
    "actualEndTime": "2024-02-15T17:10:00+00:00",
    "completionNotes": "Service réalisé avec succès..."
  }
}
```

---

### 3.7 Avis Reçus

#### Liste de mes avis

```http
GET /api/prestataire/reviews
```

**Response** (200 OK):
```json
{
  "success": true,
  "data": {
    "averageRating": 4.8,
    "totalReviews": 45,
    "reviews": [
      {
        "id": 1,
        "client": {
          "firstName": "Jean",
          "lastName": "D."
        },
        "booking": {
          "id": 1,
          "serviceCategory": "Nettoyage"
        },
        "rating": 5,
        "comment": "Excellent service...",
        "createdAt": "2024-02-16T10:00:00+00:00"
      }
    ]
  }
}
```

---

## 4. Admin

**Headers requis**:
```
Authorization: Bearer {token}
```

### 4.1 Gestion Utilisateurs

#### Liste des utilisateurs

```http
GET /api/admin/users
GET /api/admin/users?type=client
GET /api/admin/users?search=jean
```

**Query Parameters**:
- `type`: `client`, `prestataire`, `admin`
- `search`: Recherche par nom/email
- `page`, `limit`: Pagination

**Response** (200 OK):
```json
{
  "success": true,
  "data": {
    "items": [
      {
        "id": 1,
        "email": "client@example.com",
        "firstName": "Jean",
        "lastName": "Dupont",
        "userType": "client",
        "isActive": true,
        "isVerified": true,
        "createdAt": "2024-01-15T10:30:00+00:00"
      }
    ],
    "pagination": {
      "total": 250,
      "page": 1,
      "limit": 20,
      "pages": 13
    }
  }
}
```

---

#### Détails d'un utilisateur

```http
GET /api/admin/users/{id}
```

**Response** (200 OK):
```json
{
  "success": true,
  "data": {
    "id": 5,
    "email": "prestataire@example.com",
    "firstName": "Marie",
    "lastName": "Martin",
    "userType": "prestataire",
    "phone": "0698765432",
    "siret": "12345678901234",
    "isApproved": true,
    "isActive": true,
    "createdAt": "2024-01-10T09:00:00+00:00"
  }
}
```

---

#### Désactiver/Activer un utilisateur

```http
PUT /api/admin/users/{id}
```

**Body**:
```json
{
  "isActive": false
}
```

**Response** (200 OK):
```json
{
  "success": true,
  "message": "Utilisateur désactivé",
  "data": {
    "id": 1,
    "isActive": false
  }
}
```

---

### 4.2 Approbation Prestataires

#### Liste des prestataires en attente

```http
GET /api/admin/prestataires/pending
```

**Response** (200 OK):
```json
{
  "success": true,
  "data": [
    {
      "id": 10,
      "email": "nouveau@example.com",
      "firstName": "Pierre",
      "lastName": "Durant",
      "siret": "98765432109876",
      "serviceCategories": ["Nettoyage", "Repassage"],
      "documents": [
        {
          "type": "kbis",
          "status": "pending",
          "filePath": "/uploads/kbis_10.pdf"
        },
        {
          "type": "insurance",
          "status": "pending",
          "filePath": "/uploads/insurance_10.pdf"
        }
      ],
      "createdAt": "2024-01-25T10:00:00+00:00"
    }
  ]
}
```

---

#### Approuver un prestataire

```http
POST /api/admin/prestataires/{id}/approve
```

**Response** (200 OK):
```json
{
  "success": true,
  "message": "Prestataire approuvé",
  "data": {
    "id": 10,
    "isApproved": true,
    "approvedAt": "2024-01-26T14:30:00+00:00"
  }
}
```

---

#### Rejeter un prestataire

```http
POST /api/admin/prestataires/{id}/reject
```

**Body**:
```json
{
  "reason": "Documents incomplets"
}
```

**Response** (200 OK):
```json
{
  "success": true,
  "message": "Prestataire rejeté"
}
```

---

### 4.3 Gestion des Catégories et Sous-catégories

#### Liste des catégories (hiérarchique)

```http
GET /api/admin/categories
GET /api/admin/categories?includeChildren=true
```

**Query Parameters**:
- `includeChildren` (optional): Inclure les catégories enfants
- `includeSubcategories` (optional): Inclure les sous-catégories
- `parent_id` (optional): Filtrer par catégorie parente

**Response** (200 OK):
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Nettoyage",
      "slug": "nettoyage",
      "description": "Services de nettoyage professionnel",
      "icon": "cleaning-icon.svg",
      "parent_id": null,
      "level": 0,
      "displayOrder": 1,
      "isActive": true,
      "childrenCount": 3,
      "subcategoriesCount": 8,
      "createdAt": "2024-01-10T10:00:00+00:00"
    }
  ]
}
```

---

#### Créer une catégorie

```http
POST /api/admin/categories
```

**Body (catégorie racine)**:
```json
{
  "name": "Jardinage",
  "slug": "jardinage",
  "description": "Services de jardinage et entretien espaces verts",
  "icon": "garden.svg",
  "displayOrder": 4
}
```

**Body (catégorie enfant)**:
```json
{
  "name": "Entretien pelouse",
  "slug": "entretien-pelouse",
  "description": "Tonte et entretien de pelouse",
  "parent_id": 3,
  "displayOrder": 1
}
```

**Response** (201 Created):
```json
{
  "success": true,
  "message": "Catégorie créée avec succès",
  "data": {
    "id": 7,
    "name": "Entretien pelouse",
    "slug": "entretien-pelouse",
    "parent_id": 3,
    "level": 1,
    "isActive": true
  }
}
```

---

#### Modifier une catégorie

```http
PUT /api/admin/categories/{id}
```

**Body**:
```json
{
  "name": "Jardinage professionnel",
  "description": "Services de jardinage et aménagement paysager",
  "icon": "garden-pro.svg",
  "displayOrder": 3
}
```

**Response** (200 OK):
```json
{
  "success": true,
  "message": "Catégorie mise à jour",
  "data": {
    "id": 3,
    "name": "Jardinage professionnel",
    "slug": "jardinage-professionnel"
  }
}
```

---

#### Supprimer une catégorie

```http
DELETE /api/admin/categories/{id}
```

**Response** (200 OK):
```json
{
  "success": true,
  "message": "Catégorie supprimée avec succès"
}
```

**Note**: La suppression d'une catégorie supprime aussi ses enfants et sous-catégories (cascade)

---

#### Réorganiser les catégories

```http
PUT /api/admin/categories/reorder
```

**Body**:
```json
{
  "categories": [
    {"id": 1, "displayOrder": 1},
    {"id": 2, "displayOrder": 2},
    {"id": 3, "displayOrder": 3}
  ]
}
```

**Response** (200 OK):
```json
{
  "success": true,
  "message": "Ordre des catégories mis à jour"
}
```

---

#### Liste des sous-catégories

```http
GET /api/admin/subcategories
GET /api/admin/subcategories?category_id=1
GET /api/admin/subcategories?search=ménage
```

**Query Parameters**:
- `category_id` (optional): Filtrer par catégorie
- `search` (optional): Recherche par nom
- `isActive` (optional): Filtrer actives/inactives
- `page`, `limit`: Pagination

**Response** (200 OK):
```json
{
  "success": true,
  "data": {
    "items": [
      {
        "id": 3,
        "category": {
          "id": 5,
          "name": "Grand ménage"
        },
        "name": "Grand ménage classique",
        "slug": "grand-menage-classique",
        "description": "Nettoyage en profondeur",
        "basePrice": 30.00,
        "estimatedDuration": 300,
        "icon": "deep-cleaning.svg",
        "requirements": ["matériel complet"],
        "displayOrder": 1,
        "isActive": true,
        "prestataireCount": 45,
        "requestCount": 127
      }
    ],
    "pagination": {
      "total": 35,
      "page": 1,
      "limit": 20,
      "pages": 2
    }
  }
}
```

---

#### Créer une sous-catégorie

```http
POST /api/admin/subcategories
```

**Body**:
```json
{
  "category_id": 5,
  "name": "Grand ménage avec vitres",
  "slug": "grand-menage-vitres",
  "description": "Grand ménage incluant le nettoyage des vitres",
  "basePrice": 35.00,
  "estimatedDuration": 360,
  "icon": "deep-cleaning-windows.svg",
  "requirements": [
    "matériel complet",
    "kit nettoyage vitres",
    "échelle si nécessaire"
  ],
  "displayOrder": 2
}
```

**Response** (201 Created):
```json
{
  "success": true,
  "message": "Sous-catégorie créée avec succès",
  "data": {
    "id": 4,
    "category_id": 5,
    "name": "Grand ménage avec vitres",
    "slug": "grand-menage-vitres",
    "basePrice": 35.00,
    "isActive": true
  }
}
```

---

#### Modifier une sous-catégorie

```http
PUT /api/admin/subcategories/{id}
```

**Body**:
```json
{
  "name": "Grand ménage premium",
  "description": "Grand ménage avec vitres et extras",
  "basePrice": 38.00,
  "estimatedDuration": 400,
  "requirements": [
    "matériel professionnel complet",
    "kit vitres",
    "produits écologiques"
  ]
}
```

**Response** (200 OK):
```json
{
  "success": true,
  "message": "Sous-catégorie mise à jour",
  "data": {
    "id": 4,
    "name": "Grand ménage premium",
    "basePrice": 38.00
  }
}
```

---

#### Supprimer une sous-catégorie

```http
DELETE /api/admin/subcategories/{id}
```

**Response** (200 OK):
```json
{
  "success": true,
  "message": "Sous-catégorie supprimée avec succès"
}
```

**Erreur** (409 Conflict - si utilisée par des prestataires):
```json
{
  "success": false,
  "error": "Cannot delete subcategory",
  "message": "Cette sous-catégorie est utilisée par 12 prestataires. Veuillez d'abord la désactiver.",
  "data": {
    "prestataireCount": 12,
    "activeRequests": 3
  }
}
```

---

#### Activer/Désactiver une sous-catégorie

```http
PUT /api/admin/subcategories/{id}/toggle
```

**Response** (200 OK):
```json
{
  "success": true,
  "message": "Sous-catégorie désactivée",
  "data": {
    "id": 4,
    "isActive": false
  }
}
```

---

#### Statistiques d'une sous-catégorie

```http
GET /api/admin/subcategories/{id}/statistics
```

**Response** (200 OK):
```json
{
  "success": true,
  "data": {
    "subcategory": {
      "id": 3,
      "name": "Grand ménage classique",
      "basePrice": 30.00
    },
    "statistics": {
      "prestataireCount": 45,
      "activePrestataires": 38,
      "requestCount": 127,
      "completedBookings": 89,
      "averageRating": 4.7,
      "pricing": {
        "averagePrice": 32.50,
        "minPrice": 28.00,
        "maxPrice": 40.00
      },
      "revenue": {
        "total": 6700.00,
        "thisMonth": 950.00
      }
    }
  }
}
```

---

### 4.4 Statistiques

#### Statistiques globales

```http
GET /api/admin/statistics
GET /api/admin/statistics?period=month
```

**Response** (200 OK):
```json
{
  "success": true,
  "data": {
    "users": {
      "total": 500,
      "clients": 350,
      "prestataires": 145,
      "admins": 5
    },
    "bookings": {
      "total": 1250,
      "scheduled": 85,
      "completed": 1100,
      "cancelled": 65
    },
    "revenue": {
      "total": 93750.00,
      "commissions": 14062.50,
      "averageBookingAmount": 75.00
    },
    "recentActivity": [
      {
        "type": "booking_completed",
        "date": "2024-01-26T16:00:00+00:00",
        "description": "Réservation #1234 complétée"
      }
    ]
  }
}
```

---

## 6. Common (Endpoints Communs)

### 6.1 Catégories et Sous-catégories

**Note**: Les endpoints publics des catégories et sous-catégories sont documentés dans la section [2. Catégories et Sous-catégories](#2-catégories-et-sous-catégories).

Principaux endpoints disponibles :
- `GET /api/categories` - Liste des catégories racines
- `GET /api/categories/tree` - Arborescence complète
- `GET /api/categories/{id}/subcategories` - Sous-catégories d'une catégorie
- `GET /api/subcategories/{id}` - Détails d'une sous-catégorie
- `GET /api/subcategories/{id}/pricing` - Tarification

---

### 6.2 Notifications

#### Liste des notifications

```http
GET /api/notifications
GET /api/notifications?unread=true
```

**Response** (200 OK):
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "type": "booking_confirmed",
      "subject": "Réservation confirmée",
      "message": "Votre réservation pour le 15/02/2024 est confirmée",
      "isRead": false,
      "createdAt": "2024-01-20T16:00:00+00:00"
    }
  ]
}
```

---

#### Marquer comme lue

```http
PUT /api/notifications/{id}/read
```

**Response** (200 OK):
```json
{
  "success": true,
  "message": "Notification marquée comme lue"
}
```

---

## 6. Module Financial

### 6.1 Client - Paiements

#### Créer un paiement

```http
POST /api/client/financial/payments
```

**Body**:
```json
{
  "bookingId": 1,
  "paymentMethodId": "pm_1234567890"
}
```

**Response** (201 Created):
```json
{
  "success": true,
  "message": "Paiement effectué avec succès",
  "data": {
    "id": 1,
    "bookingId": 1,
    "amount": 75.00,
    "currency": "EUR",
    "paymentMethod": "card",
    "status": "completed",
    "paidAt": "2024-01-20T16:00:00+00:00",
    "gatewayTransactionId": "ch_1234567890"
  }
}
```

---

#### Liste des paiements

```http
GET /api/client/financial/payments
```

**Response** (200 OK):
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "booking": {
        "id": 1,
        "scheduledDate": "2024-02-15",
        "prestataire": {
          "firstName": "Marie",
          "lastName": "Martin"
        }
      },
      "amount": 75.00,
      "status": "completed",
      "paidAt": "2024-01-20T16:00:00+00:00"
    }
  ]
}
```

---

#### Détails d'un paiement

```http
GET /api/client/financial/payments/{id}
```

**Response** (200 OK):
```json
{
  "success": true,
  "data": {
    "id": 1,
    "bookingId": 1,
    "amount": 75.00,
    "currency": "EUR",
    "paymentMethod": "card",
    "paymentGateway": "stripe",
    "gatewayTransactionId": "ch_1234567890",
    "status": "completed",
    "paidAt": "2024-01-20T16:00:00+00:00",
    "invoice": {
      "id": 1,
      "invoiceNumber": "INV-2024-00001",
      "pdfPath": "/invoices/INV-2024-00001.pdf"
    }
  }
}
```

---

#### Demander un remboursement

```http
POST /api/client/financial/refunds/request
```

**Body**:
```json
{
  "paymentId": 1,
  "amount": 75.00,
  "reason": "Service non conforme"
}
```

**Response** (201 Created):
```json
{
  "success": true,
  "message": "Demande de remboursement créée",
  "data": {
    "id": 1,
    "paymentId": 1,
    "amount": 75.00,
    "status": "requested",
    "requestedAt": "2024-02-20T10:00:00+00:00"
  }
}
```

---

#### Liste des factures

```http
GET /api/client/financial/invoices
```

**Response** (200 OK):
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "invoiceNumber": "INV-2024-00001",
      "booking": {
        "id": 1,
        "scheduledDate": "2024-02-15"
      },
      "amount": 75.00,
      "taxAmount": 0.00,
      "totalAmount": 75.00,
      "status": "paid",
      "issueDate": "2024-01-20",
      "paidDate": "2024-01-20",
      "pdfPath": "/invoices/INV-2024-00001.pdf"
    }
  ]
}
```

---

#### Télécharger une facture

```http
GET /api/client/financial/invoices/{id}/download
```

**Response**: PDF File

---

### 6.2 Prestataire - Gains

#### Liste des gains

```http
GET /api/prestataire/financial/earnings
GET /api/prestataire/financial/earnings?status=available
GET /api/prestataire/financial/earnings?startDate=2024-01-01&endDate=2024-01-31
```

**Response** (200 OK):
```json
{
  "success": true,
  "data": {
    "items": [
      {
        "id": 1,
        "booking": {
          "id": 1,
          "scheduledDate": "2024-02-15",
          "client": {
            "firstName": "Jean",
            "lastName": "D."
          }
        },
        "totalAmount": 75.00,
        "commissionAmount": 11.25,
        "commissionRate": 15.00,
        "netAmount": 63.75,
        "status": "available",
        "earnedAt": "2024-02-15T17:30:00+00:00",
        "availableAt": "2024-02-22T17:30:00+00:00"
      }
    ],
    "summary": {
      "totalEarnings": 1275.00,
      "totalCommissions": 191.25,
      "totalNet": 1083.75,
      "availableBalance": 425.50,
      "pendingBalance": 658.25
    }
  }
}
```

---

#### Statistiques des gains

```http
GET /api/prestataire/financial/earnings/statistics
GET /api/prestataire/financial/earnings/statistics?period=month
```

**Response** (200 OK):
```json
{
  "success": true,
  "data": {
    "currentMonth": {
      "totalEarnings": 450.00,
      "totalNet": 382.50,
      "bookingsCount": 6,
      "averagePerBooking": 75.00
    },
    "lastMonth": {
      "totalEarnings": 525.00,
      "totalNet": 446.25,
      "bookingsCount": 7
    },
    "yearToDate": {
      "totalEarnings": 5250.00,
      "totalNet": 4462.50,
      "bookingsCount": 70
    },
    "chartData": [
      {
        "month": "2024-01",
        "earnings": 525.00,
        "net": 446.25
      },
      {
        "month": "2024-02",
        "earnings": 450.00,
        "net": 382.50
      }
    ]
  }
}
```

---

#### Solde disponible

```http
GET /api/prestataire/financial/balance
```

**Response** (200 OK):
```json
{
  "success": true,
  "data": {
    "availableBalance": 425.50,
    "pendingBalance": 658.25,
    "totalBalance": 1083.75,
    "minimumPayoutAmount": 50.00,
    "canRequestPayout": true,
    "nextAvailableDate": "2024-02-28T00:00:00+00:00"
  }
}
```

---

#### Demander un versement

```http
POST /api/prestataire/financial/payouts/request
```

**Body**:
```json
{
  "amount": 425.50,
  "paymentMethod": "bank_transfer",
  "bankDetails": {
    "iban": "FR7612345678901234567890123",
    "bic": "BNPAFRPPXXX",
    "accountHolder": "Marie Martin"
  }
}
```

**Response** (201 Created):
```json
{
  "success": true,
  "message": "Demande de versement créée",
  "data": {
    "id": 1,
    "amount": 425.50,
    "status": "pending",
    "paymentMethod": "bank_transfer",
    "earningsCount": 5,
    "requestedAt": "2024-02-25T10:00:00+00:00"
  }
}
```

---

#### Liste des versements

```http
GET /api/prestataire/financial/payouts
```

**Response** (200 OK):
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "amount": 425.50,
      "status": "pending",
      "paymentMethod": "bank_transfer",
      "earningsCount": 5,
      "requestedAt": "2024-02-25T10:00:00+00:00",
      "approvedAt": null,
      "processedAt": null
    },
    {
      "id": 2,
      "amount": 350.00,
      "status": "completed",
      "paymentMethod": "bank_transfer",
      "requestedAt": "2024-01-25T10:00:00+00:00",
      "approvedAt": "2024-01-26T14:00:00+00:00",
      "processedAt": "2024-01-27T09:00:00+00:00",
      "transactionReference": "PAYOUT-2024-00001"
    }
  ]
}
```

---

#### Historique des transactions

```http
GET /api/prestataire/financial/transactions
GET /api/prestataire/financial/transactions?type=earning
```

**Response** (200 OK):
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "type": "earning",
      "amount": 63.75,
      "status": "completed",
      "reference": "TXN-EARN-00001",
      "booking": {
        "id": 1,
        "scheduledDate": "2024-02-15"
      },
      "createdAt": "2024-02-15T17:30:00+00:00"
    },
    {
      "id": 2,
      "type": "payout",
      "amount": -350.00,
      "status": "completed",
      "reference": "TXN-PAYOUT-00001",
      "payout": {
        "id": 2,
        "transactionReference": "PAYOUT-2024-00001"
      },
      "createdAt": "2024-01-27T09:00:00+00:00"
    }
  ]
}
```

---

### 6.3 Admin - Gestion Financière

#### Dashboard financier

```http
GET /api/admin/financial/dashboard
GET /api/admin/financial/dashboard?period=month
```

**Response** (200 OK):
```json
{
  "success": true,
  "data": {
    "revenue": {
      "totalRevenue": 93750.00,
      "totalCommissions": 14062.50,
      "netRevenue": 79687.50,
      "averageCommissionRate": 15.0
    },
    "bookings": {
      "total": 1250,
      "completed": 1100,
      "averageAmount": 75.00
    },
    "payouts": {
      "pending": 15,
      "pendingAmount": 5250.00,
      "processedThisMonth": 45,
      "processedAmount": 18750.00
    },
    "chartData": {
      "revenue": [
        {
          "date": "2024-01",
          "revenue": 7500.00,
          "commissions": 1125.00
        }
      ]
    }
  }
}
```

---

#### Liste des commissions

```http
GET /api/admin/financial/commissions
GET /api/admin/financial/commissions?startDate=2024-01-01
```

**Response** (200 OK):
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "booking": {
        "id": 1,
        "scheduledDate": "2024-02-15",
        "prestataire": {
          "firstName": "Marie",
          "lastName": "Martin"
        }
      },
      "baseAmount": 75.00,
      "commissionRate": 15.00,
      "commissionAmount": 11.25,
      "calculationMethod": "percentage",
      "createdAt": "2024-02-15T17:30:00+00:00"
    }
  ]
}
```

---

#### Règles de commission

##### Liste des règles

```http
GET /api/admin/financial/commission-rules
```

**Response** (200 OK):
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Commission standard",
      "type": "percentage",
      "value": 15.00,
      "category": null,
      "isActive": true,
      "priority": 0,
      "validFrom": "2024-01-01T00:00:00+00:00",
      "validUntil": null
    },
    {
      "id": 2,
      "name": "Commission réduite nettoyage",
      "type": "percentage",
      "value": 12.00,
      "category": {
        "id": 1,
        "name": "Nettoyage"
      },
      "minAmount": 100.00,
      "isActive": true,
      "priority": 1
    }
  ]
}
```

---

##### Créer une règle

```http
POST /api/admin/financial/commission-rules
```

**Body**:
```json
{
  "name": "Commission VIP",
  "type": "percentage",
  "value": 10.00,
  "minAmount": 200.00,
  "categoryId": null,
  "conditions": {
    "minBookings": 50
  },
  "priority": 2,
  "validFrom": "2024-03-01T00:00:00+00:00"
}
```

**Response** (201 Created):
```json
{
  "success": true,
  "message": "Règle de commission créée",
  "data": {
    "id": 3,
    "name": "Commission VIP",
    "type": "percentage",
    "value": 10.00,
    "isActive": true
  }
}
```

---

#### Versements en attente

```http
GET /api/admin/financial/payouts/pending
```

**Response** (200 OK):
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "prestataire": {
        "id": 5,
        "firstName": "Marie",
        "lastName": "Martin",
        "email": "marie.martin@example.com"
      },
      "amount": 425.50,
      "status": "pending",
      "earningsCount": 5,
      "bankDetails": {
        "iban": "FR76*********************23",
        "accountHolder": "Marie Martin"
      },
      "requestedAt": "2024-02-25T10:00:00+00:00"
    }
  ]
}
```

---

#### Approuver un versement

```http
POST /api/admin/financial/payouts/{id}/approve
```

**Response** (200 OK):
```json
{
  "success": true,
  "message": "Versement approuvé",
  "data": {
    "id": 1,
    "status": "approved",
    "approvedAt": "2024-02-26T14:00:00+00:00"
  }
}
```

---

#### Traiter un versement

```http
POST /api/admin/financial/payouts/{id}/process
```

**Response** (200 OK):
```json
{
  "success": true,
  "message": "Versement traité avec succès",
  "data": {
    "id": 1,
    "status": "completed",
    "processedAt": "2024-02-27T09:00:00+00:00",
    "transactionReference": "PAYOUT-2024-00015"
  }
}
```

---

#### Transactions globales

```http
GET /api/admin/financial/transactions
GET /api/admin/financial/transactions?type=commission
GET /api/admin/financial/transactions?userId=5
```

**Response** (200 OK):
```json
{
  "success": true,
  "data": {
    "items": [
      {
        "id": 1,
        "type": "commission",
        "user": {
          "id": 1,
          "firstName": "Jean",
          "lastName": "Dupont"
        },
        "amount": 11.25,
        "status": "completed",
        "reference": "TXN-COM-00001",
        "booking": {
          "id": 1
        },
        "createdAt": "2024-02-15T17:30:00+00:00"
      }
    ],
    "pagination": {
      "total": 5000,
      "page": 1,
      "limit": 50,
      "pages": 100
    }
  }
}
```

---

#### Rapports financiers

```http
GET /api/admin/financial/reports/monthly?year=2024&month=2
GET /api/admin/financial/reports/annual?year=2024
```

**Response** (200 OK):
```json
{
  "success": true,
  "data": {
    "period": "2024-02",
    "revenue": {
      "totalBookings": 105,
      "totalRevenue": 7875.00,
      "totalCommissions": 1181.25,
      "averageCommissionRate": 15.0
    },
    "payouts": {
      "totalPayouts": 12,
      "totalAmount": 6750.00
    },
    "topPrestataires": [
      {
        "id": 5,
        "name": "Marie Martin",
        "bookings": 18,
        "revenue": 1350.00,
        "netEarnings": 1147.50
      }
    ]
  }
}
```

---

## 7. Codes de Statut HTTP

| Code | Signification | Usage |
|------|---------------|-------|
| 200 | OK | Requête réussie |
| 201 | Created | Ressource créée avec succès |
| 204 | No Content | Suppression réussie |
| 400 | Bad Request | Données invalides |
| 401 | Unauthorized | Non authentifié |
| 403 | Forbidden | Accès refusé |
| 404 | Not Found | Ressource non trouvée |
| 422 | Unprocessable Entity | Erreur de validation |
| 500 | Internal Server Error | Erreur serveur |

---

## 8. Modèles de Données

### 8.1 Erreurs Standard

```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Les données fournies sont invalides",
    "details": {
      "email": ["L'email est obligatoire"],
      "password": ["Le mot de passe doit contenir au moins 8 caractères"]
    }
  }
}
```

---

### 8.2 Pagination

Toutes les listes paginées suivent ce format :

```json
{
  "success": true,
  "data": {
    "items": [...],
    "pagination": {
      "total": 150,
      "page": 1,
      "limit": 10,
      "pages": 15
    }
  }
}
```

---

### 8.3 Headers d'Authentification

```
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc...
Content-Type: application/json
Accept: application/json
```

---

### 8.4 Modèles Principaux

#### ServiceCategory (Catégorie)

```typescript
{
  id: number;
  name: string;
  slug: string;
  description?: string;
  icon?: string;
  parent_id?: number;
  parent?: ServiceCategory;
  children?: ServiceCategory[];
  level: number;           // 0 = racine, 1+ = enfants
  displayOrder: number;
  isActive: boolean;
  subcategoriesCount?: number;
  childrenCount?: number;
  createdAt: string;       // ISO 8601
  updatedAt?: string;
}
```

#### ServiceSubcategory (Sous-catégorie)

```typescript
{
  id: number;
  category_id: number;
  category?: {
    id: number;
    name: string;
    slug: string;
  };
  name: string;
  slug: string;
  description?: string;
  basePrice: number;       // Tarif de base (€/h)
  estimatedDuration: number;  // Durée estimée (minutes)
  icon?: string;
  requirements?: string[]; // Équipements requis
  displayOrder: number;
  isActive: boolean;
  prestataireCount?: number;
  requestCount?: number;
  averagePrice?: number;
  createdAt: string;
  updatedAt?: string;
}
```

#### ServiceRequest (Demande modifiée)

```typescript
{
  id: number;
  client_id: number;
  category_id: number;
  category: {
    id: number;
    name: string;
  };
  subcategory_id?: number;  // ✅ NOUVEAU
  subcategory?: {           // ✅ NOUVEAU
    id: number;
    name: string;
    basePrice: number;
    estimatedDuration: number;
  };
  title: string;
  description: string;
  address: string;
  city: string;
  postalCode: string;
  latitude?: number;
  longitude?: number;
  preferredDate?: string;   // ISO 8601
  alternativeDates?: string[];
  estimatedDuration?: number;
  frequency: 'once' | 'weekly' | 'biweekly' | 'monthly';
  budget?: number;
  surfaceArea?: number;     // ✅ NOUVEAU - m²
  specificRequirements?: string[];  // ✅ NOUVEAU
  status: 'open' | 'quoted' | 'in_progress' | 'completed' | 'cancelled';
  quotesCount?: number;
  createdAt: string;
  expiresAt?: string;
}
```

#### PrestataireSpecialization (Spécialisation)

```typescript
{
  id: number;
  prestataire_id: number;
  subcategory_id: number;
  subcategory: ServiceSubcategory;
  hourlyRate: number;      // Tarif personnalisé du prestataire
  experience?: string;     // Description expérience
  isActive: boolean;
  addedAt: string;
}
```

---

### 8.5 Filtres et Recherche

La plupart des endpoints de liste supportent :

- `page`: Numéro de page (défaut: 1)
- `limit`: Résultats par page (défaut: 10, max: 50)
- `sort`: Champ de tri (ex: `createdAt`)
- `order`: Ordre de tri (`asc` ou `desc`)
- `search`: Recherche textuelle

**Exemple**:
```
GET /api/client/bookings?page=2&limit=20&sort=scheduledDate&order=desc&search=nettoyage
```

---

## 📝 Notes pour le Développement Frontend

### Gestion des Tokens

1. **Stockage**: Stocker le token JWT dans `localStorage` ou `sessionStorage`
2. **Rafraîchissement**: Utiliser le refresh token avant expiration du token principal
3. **Déconnexion**: Supprimer les tokens du storage

### Intercepteurs Axios (Exemple)

```javascript
// axios.config.js
import axios from 'axios';

const api = axios.create({
  baseURL: 'http://localhost:8000/api',
  headers: {
    'Content-Type': 'application/json',
  },
});

// Intercepteur pour ajouter le token
api.interceptors.request.use((config) => {
  const token = localStorage.getItem('token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

// Intercepteur pour gérer les erreurs
api.interceptors.response.use(
  (response) => response,
  async (error) => {
    if (error.response?.status === 401) {
      // Token expiré, tenter de rafraîchir
      const refreshToken = localStorage.getItem('refreshToken');
      if (refreshToken) {
        try {
          const { data } = await axios.post('/api/refresh-token', {
            refreshToken,
          });
          localStorage.setItem('token', data.data.token);
          // Réessayer la requête
          return api(error.config);
        } catch {
          // Échec du rafraîchissement, déconnecter
          localStorage.clear();
          window.location.href = '/login';
        }
      }
    }
    return Promise.reject(error);
  }
);

export default api;
```

---

**Cette documentation permet un développement frontend complètement indépendant du backend !** 🚀