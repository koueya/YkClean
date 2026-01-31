# Solution au problème de cohérence des services Planning

## Problème identifié

Le `AbsenceController` référençait un service `PlanningService` qui n'existait pas, alors que nous avions :
- `AvailabilityManager` : pour gérer les disponibilités
- `ConflictDetector` : pour détecter les conflits
- `ScheduleOptimizer` : pour optimiser le planning
- Mais aucun service unifié pour orchestrer tout cela

## Solution implémentée

### 1. Création d'`AvailabilityService`

J'ai créé un service **`AvailabilityService`** (meilleur nom que `PlanningService`) qui :

**Centralise la gestion** :
- ✅ Disponibilités (délègue à `AvailabilityManager`)
- ✅ Absences (gestion directe)
- ✅ Planning complet (orchestration)
- ✅ Vérifications de conflits (utilise `ConflictDetector`)

**Avantages** :
- Un seul point d'entrée pour toutes les opérations liées au planning
- Cohérence avec la structure existante
- Réutilise `AvailabilityManager` au lieu de dupliquer le code
- Découplage clair des responsabilités

### 2. Architecture finale des services Planning

```
Service/Planning/
├── AvailabilityService.php      ← SERVICE PRINCIPAL (nouveau)
│   ├── Gestion des disponibilités (délègue à AvailabilityManager)
│   ├── Gestion des absences (direct)
│   ├── Récupération du planning complet
│   └── Statistiques et optimisations
│
├── AvailabilityManager.php      ← GESTION DISPONIBILITÉS
│   ├── CRUD disponibilités
│   ├── Vérification disponibilité
│   ├── Calcul créneaux libres
│   └── Taux d'occupation
│
├── ConflictDetector.php          ← DÉTECTION CONFLITS
│   ├── Détection chevauchements
│   ├── Vérification contraintes
│   └── Suggestions résolution
│
└── ScheduleOptimizer.php         ← OPTIMISATION
    ├── Optimisation planning
    ├── Optimisation trajets
    └── Équilibrage charge
```

### 3. Mise à jour du contrôleur

`AbsenceController` doit maintenant injecter `AvailabilityService` :

```php
public function __construct(
    private AvailabilityService $availabilityService,  // ← Changé ici
    private NotificationService $notificationService,
    private ReplacementService $replacementService,
    private EntityManagerInterface $entityManager,
    private ValidatorInterface $validator,
    private LoggerInterface $logger
) {}
```

Et utiliser les méthodes d'`AvailabilityService` :

```php
// Ancien code (ne fonctionne pas)
$affectedBookings = $this->planningService->getBookingsInPeriod(...);
$absence = $this->planningService->createAbsence(...);

// Nouveau code (fonctionne)
$affectedBookings = $this->availabilityService->getBookingsInPeriod(...);
$absence = $this->availabilityService->createAbsence(...);
```

### 4. Points à modifier dans tous les contrôleurs

Recherchez et remplacez dans **tous les contrôleurs** :

```php
// ANCIEN
use App\Service\Planning\PlanningService;
private PlanningService $planningService;

// NOUVEAU
use App\Service\Planning\AvailabilityService;
private AvailabilityService $availabilityService;
```

## Méthodes principales d'AvailabilityService

### Disponibilités
```php
createAvailability()      // Crée une disponibilité
updateAvailability()      // Met à jour
deleteAvailability()      // Supprime
isAvailable()             // Vérifie disponibilité
getAvailableSlots()       // Récupère créneaux libres
blockDates()              // Bloque des dates
calculateOccupancyRate()  // Calcule taux occupation
```

### Absences
```php
createAbsence()           // Crée une absence
updateAbsence()           // Met à jour
cancelAbsence()           // Annule
getAbsencesInPeriod()     // Récupère absences période
```

### Planning
```php
getBookingsInPeriod()     // Réservations période
isPeriodFree()            // Période libre?
getCompleteSchedule()     // Planning complet
getWeeklySchedule()       // Planning semaine
getMonthlySchedule()      // Planning mois
canAddBooking()           // Peut ajouter réservation?
getPlanningStats()        // Statistiques
suggestOptimizations()    // Suggestions
findNextAvailableSlot()   // Prochain créneau libre
```

## Fichiers à créer/modifier

### ✅ Fichiers créés
1. **`backend/src/Service/Planning/AvailabilityService.php`** (nouveau)

### 📝 Fichiers à modifier
2. **`backend/src/Controller/Api/Prestataire/AbsenceController.php`**
   - Remplacer `PlanningService` par `AvailabilityService`
   - Remplacer tous les `$this->planningService` par `$this->availabilityService`

3. **Tout autre contrôleur** qui utilise `PlanningService`
   - Faire la même substitution

## Commandes pour appliquer les changements

```bash
# 1. Rechercher tous les fichiers utilisant PlanningService
grep -r "PlanningService" backend/src/Controller/

# 2. Pour chaque fichier trouvé, remplacer :
# - Dans les imports
# - Dans le constructeur
# - Dans les appels de méthodes

# 3. Vérifier qu'il n'y a pas d'erreurs
symfony console lint:container
```

## Résumé

| Avant | Après |
|-------|-------|
| ❌ `PlanningService` (n'existe pas) | ✅ `AvailabilityService` (existe) |
| ❌ Code dispersé | ✅ Code centralisé |
| ❌ Incohérences | ✅ Architecture claire |

**Avantage principal** : Un seul service (`AvailabilityService`) qui orchestre tout ce qui concerne le planning, les disponibilités et les absences, tout en réutilisant les services spécialisés existants.