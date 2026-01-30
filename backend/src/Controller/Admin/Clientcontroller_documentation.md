# ClientController Admin - Documentation complète

## 📋 Vue d'ensemble

Le `ClientController` gère toutes les opérations administratives liées aux clients de la plateforme.

**Namespace**: `App\Controller\Admin\ClientController`  
**Route de base**: `/admin/clients`  
**Sécurité**: `#[IsGranted('ROLE_ADMIN')]`

---

## 🎯 Actions disponibles (17 endpoints)

### 1. Liste des clients
```php
GET /admin/clients
```

**Fonctionnalités**:
- ✅ Pagination (20 par page)
- ✅ Recherche (nom, email, téléphone)
- ✅ Filtres par statut
- ✅ Statistiques globales

**Filtres disponibles**:
```
?status=active      → Clients actifs
?status=inactive    → Clients inactifs
?status=verified    → Email vérifié
?status=unverified  → Email non vérifié
?search=Dupont      → Recherche textuelle
?page=2             → Pagination
```

**Statistiques affichées**:
```php
[
    'total' => 1234,
    'active' => 1150,
    'inactive' => 84,
    'verified' => 1100,
    'unverified' => 134,
]
```

---

### 2. Détails d'un client
```php
GET /admin/clients/{id}
```

**Affiche**:
- Informations complètes du client
- Statistiques personnelles
- Dernières demandes de service (10)
- Dernières réservations (10)
- Méthodes de paiement Stripe

**Statistiques calculées**:
```php
[
    'serviceRequestsCount' => 25,      // Total demandes
    'bookingsCount' => 18,             // Total réservations
    'bookingsCompleted' => 15,         // Terminées
    'completionRate' => 83.3,          // % complétion
    'totalSpent' => '1250.50',         // Dépenses totales
    'avgSpentPerBooking' => '83.37',   // Moyenne/réservation
]
```

**Formules**:
```php
completionRate = (bookingsCompleted / bookingsCount) * 100

avgSpentPerBooking = totalSpent / bookingsCompleted

totalSpent = SUM(payments.amount) 
WHERE booking.client = X AND payment.status = 'paid'
```

---

### 3. Activer un client
```php
POST /admin/clients/{id}/activate
```

**Action**: 
- `isActive` = true
- Flash message succès

---

### 4. Désactiver un client
```php
POST /admin/clients/{id}/deactivate
```

**Paramètres**:
```
reason: string (optionnel) - Raison de la désactivation
```

**Actions**:
- `isActive` = false
- TODO: Envoyer email avec raison

---

### 5. Vérifier l'email manuellement
```php
POST /admin/clients/{id}/verify
```

**Action**:
- Appelle `$client->verifyEmail()`
- `isVerified` = true
- Efface le token de vérification

---

### 6. Supprimer un client
```php
POST /admin/clients/{id}/delete
```

**Sécurité**:
```php
// Vérifie qu'il n'a pas de réservations actives
$activeBookings = count([
    'status' => ['pending', 'confirmed', 'in_progress']
]);

if ($activeBookings > 0) {
    // BLOQUE la suppression
    return error('Réservations en cours');
}
```

---

### 7. Modifier les informations
```php
GET/POST /admin/clients/{id}/edit
```

**Champs modifiables**:
- `firstName`, `lastName`
- `email`, `phone`
- `address`, `city`, `postalCode`

**Formulaire**:
```html
<form method="post">
    <input name="firstName" value="{{ client.firstName }}">
    <input name="lastName" value="{{ client.lastName }}">
    <input name="email" value="{{ client.email }}">
    <input name="phone" value="{{ client.phone }}">
    <input name="address" value="{{ client.address }}">
    <input name="city" value="{{ client.city }}">
    <input name="postalCode" value="{{ client.postalCode }}">
    <button type="submit">Enregistrer</button>
</form>
```

---

### 8. Voir les transactions Stripe
```php
GET /admin/clients/{id}/transactions
```

**Prérequis**: Client doit avoir un `stripeCustomerId`

**Affiche**: 50 dernières transactions Stripe

**Format retour**:
```php
[
    [
        'id' => 'ch_xxx',
        'amount' => 50.00,
        'currency' => 'eur',
        'status' => 'succeeded',
        'description' => 'Service Ménage',
        'created' => '2024-01-30 14:30:00',
        'receipt_url' => 'https://...',
    ],
    // ...
]
```

---

### 9. Créer un client Stripe
```php
POST /admin/clients/{id}/stripe/create
```

**Action**:
- Appelle `StripeService->getOrCreateCustomer($client)`
- Enregistre le `stripeCustomerId` en BDD
- Flash message confirmation

**Données envoyées à Stripe**:
```php
[
    'email' => 'client@example.com',
    'name' => 'Jean Dupont',
    'phone' => '0612345678',
    'address' => [...],
    'metadata' => [
        'client_id' => 123,
        'user_type' => 'client',
    ],
]
```

---

### 10. Statistiques globales
```php
GET /admin/clients/stats/global
```

**Données fournies**:

**Stats générales**:
```php
[
    'total' => 1234,
    'active' => 1150,
    'inactive' => 84,
    'verified' => 1100,
    'verificationRate' => 89.1,  // %
]
```

**Par mois** (12 mois):
```php
'byMonth' => [65, 78, 85, 92, 88, 95, 102, 110, 98, 105, 115, 125]
```

**Top 10 clients** (par dépenses):
```php
[
    [
        'id' => 123,
        'firstName' => 'Jean',
        'lastName' => 'Dupont',
        'email' => 'jean@example.com',
        'totalSpent' => 2340.50,
        'bookingsCount' => 28,
    ],
    // ...
]
```

**Distribution géographique** (top 10 villes):
```php
[
    ['city' => 'Lyon', 'clientCount' => 450],
    ['city' => 'Paris', 'clientCount' => 380],
    ['city' => 'Marseille', 'clientCount' => 220],
    // ...
]
```

---

### 11. Export CSV
```php
GET /admin/clients/export/csv
```

**Colonnes du CSV**:
```
ID, Prénom, Nom, Email, Téléphone, Adresse, 
Code postal, Ville, Actif, Vérifié, Nombre connexions, 
Dernière connexion, Date inscription
```

**Nom du fichier**: `clients_2024-01-30.csv`

**Headers HTTP**:
```
Content-Type: text/csv
Content-Disposition: attachment; filename="clients_2024-01-30.csv"
```

---

### 12. Envoyer une notification
```php
POST /admin/clients/{id}/notify
```

**Paramètres requis**:
```json
{
    "subject": "Sujet du message",
    "message": "Contenu du message"
}
```

**Réponse JSON**:
```json
{
    "success": true,
    "message": "Notification envoyée"
}
```

**TODO**: Implémenter envoi email Symfony Mailer

---

### 13. Réinitialiser le mot de passe
```php
POST /admin/clients/{id}/reset-password
```

**Actions**:
1. Génère un token de réinitialisation (64 chars)
2. Expire dans 1 heure
3. TODO: Envoie email avec lien

**Token généré**:
```php
$client->generatePasswordResetToken();
// Génère: bin2hex(random_bytes(32))
// Enregistre dans: passwordResetToken
// Expire: passwordResetTokenExpiresAt (+1 hour)
```

---

### 14. Historique d'activité
```php
GET /admin/clients/{id}/activity
```

**Agrège toutes les activités**:
- ✅ Demandes de service
- ✅ Réservations
- ✅ Paiements

**Format unifié**:
```php
[
    [
        'type' => 'service_request',
        'date' => DateTime,
        'title' => 'Demande de service créée',
        'description' => 'Ménage',
        'status' => 'open',
    ],
    [
        'type' => 'booking',
        'date' => DateTime,
        'title' => 'Réservation créée',
        'description' => 'Avec Marie Martin',
        'status' => 'confirmed',
    ],
    [
        'type' => 'payment',
        'date' => DateTime,
        'title' => 'Paiement',
        'description' => '50.00€',
        'status' => 'paid',
    ],
]
```

**Tri**: Par date décroissante

---

## 🔒 Sécurité

### Protection des routes
```php
#[Route('/admin/clients')]
#[IsGranted('ROLE_ADMIN')]
```

### Vérifications avant suppression
```php
// Ne peut pas supprimer si réservations actives
if (hasActiveBookings()) {
    throw error('Réservations en cours');
}
```

### Données sensibles
- Mots de passe jamais affichés
- Tokens non exposés dans les vues
- Stripe Customer ID protégé

---

## 📦 Dépendances injectées

```php
✅ EntityManagerInterface      - Persistence
✅ ClientRepository            - Requêtes clients
✅ BookingRepository           - Stats réservations
✅ PaymentRepository           - Stats paiements
✅ ServiceRequestRepository    - Demandes de service
✅ StripeService              - Intégration Stripe
✅ PaginatorInterface         - Pagination
```

---

## 💡 Exemples d'utilisation

### Template Liste (index.html.twig)
```twig
{# Statistiques #}
<div class="stats">
    <div class="stat-card">
        <h3>{{ stats.total }}</h3>
        <p>Total clients</p>
    </div>
    <div class="stat-card">
        <h3>{{ stats.verified }}</h3>
        <p>Email vérifié</p>
    </div>
</div>

{# Filtres #}
<div class="filters">
    <a href="?status=active" 
       class="{{ currentStatus == 'active' ? 'active' : '' }}">
        Actifs ({{ stats.active }})
    </a>
    <a href="?status=verified">
        Vérifiés ({{ stats.verified }})
    </a>
</div>

{# Recherche #}
<form method="get">
    <input type="text" name="search" value="{{ search }}" 
           placeholder="Rechercher un client...">
    <button type="submit">Rechercher</button>
</form>

{# Table #}
<table class="table">
    <thead>
        <tr>
            <th>ID</th>
            <th>Nom</th>
            <th>Email</th>
            <th>Statut</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        {% for client in clients %}
        <tr>
            <td>{{ client.id }}</td>
            <td>{{ client.fullName }}</td>
            <td>{{ client.email }}</td>
            <td>
                <span class="badge bg-{{ client.isActive ? 'success' : 'danger' }}">
                    {{ client.isActive ? 'Actif' : 'Inactif' }}
                </span>
            </td>
            <td>
                <a href="{{ path('admin_client_show', {id: client.id}) }}" 
                   class="btn btn-sm btn-primary">
                    Voir
                </a>
            </td>
        </tr>
        {% endfor %}
    </tbody>
</table>

{# Pagination #}
{{ knp_pagination_render(clients) }}
```

---

### Template Détails (show.html.twig)
```twig
<h1>{{ client.fullName }}</h1>

{# Badges statut #}
<div class="badges">
    {% if client.isActive %}
        <span class="badge bg-success">Actif</span>
    {% else %}
        <span class="badge bg-danger">Inactif</span>
    {% endif %}
    
    {% if client.isVerified %}
        <span class="badge bg-info">Email vérifié</span>
    {% endif %}
</div>

{# Stats #}
<div class="row">
    <div class="col-md-3">
        <div class="stat-card">
            <h3>{{ stats.bookingsCount }}</h3>
            <p>Réservations</p>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <h3>{{ stats.completionRate }}%</h3>
            <p>Taux de complétion</p>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <h3>{{ stats.totalSpent }}€</h3>
            <p>Dépenses totales</p>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <h3>{{ stats.avgSpentPerBooking }}€</h3>
            <p>Moyenne / réservation</p>
        </div>
    </div>
</div>

{# Actions #}
<div class="actions">
    {% if not client.isActive %}
        <form method="post" 
              action="{{ path('admin_client_activate', {id: client.id}) }}" 
              style="display:inline">
            <button class="btn btn-success">Activer</button>
        </form>
    {% else %}
        <button class="btn btn-danger" 
                data-bs-toggle="modal" 
                data-bs-target="#deactivateModal">
            Désactiver
        </button>
    {% endif %}
    
    {% if not client.isVerified %}
        <form method="post" 
              action="{{ path('admin_client_verify', {id: client.id}) }}" 
              style="display:inline">
            <button class="btn btn-info">Vérifier email</button>
        </form>
    {% endif %}
    
    <a href="{{ path('admin_client_edit', {id: client.id}) }}" 
       class="btn btn-primary">
        Modifier
    </a>
    
    <a href="{{ path('admin_client_transactions', {id: client.id}) }}" 
       class="btn btn-secondary">
        Transactions
    </a>
    
    <a href="{{ path('admin_client_activity', {id: client.id}) }}" 
       class="btn btn-info">
        Historique
    </a>
</div>

{# Dernières réservations #}
<h3>Dernières réservations</h3>
<table class="table">
    <thead>
        <tr>
            <th>ID</th>
            <th>Prestataire</th>
            <th>Service</th>
            <th>Date</th>
            <th>Montant</th>
            <th>Statut</th>
        </tr>
    </thead>
    <tbody>
        {% for booking in recentBookings %}
        <tr>
            <td>#{{ booking.id }}</td>
            <td>{{ booking.prestataire.fullName }}</td>
            <td>{{ booking.serviceRequest.category.name }}</td>
            <td>{{ booking.scheduledDate|date('d/m/Y') }}</td>
            <td>{{ booking.amount }}€</td>
            <td>
                <span class="badge bg-{{ booking.statusColor }}">
                    {{ booking.status }}
                </span>
            </td>
        </tr>
        {% endfor %}
    </tbody>
</table>

{# Modal désactivation #}
<div class="modal fade" id="deactivateModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" 
                  action="{{ path('admin_client_deactivate', {id: client.id}) }}">
                <div class="modal-header">
                    <h5 class="modal-title">Désactiver le client</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Raison de la désactivation</label>
                        <textarea name="reason" class="form-control" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        Annuler
                    </button>
                    <button type="submit" class="btn btn-danger">
                        Désactiver
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
```

---

## 📊 Requêtes SQL générées

### Statistiques client (show)
```sql
-- Total dépensé
SELECT SUM(p.amount)
FROM payments p
INNER JOIN bookings b ON p.booking_id = b.id
WHERE b.client_id = 123 
  AND p.status = 'paid';

-- Réservations terminées
SELECT COUNT(*)
FROM bookings
WHERE client_id = 123 
  AND status = 'completed';
```

### Top clients (stats)
```sql
SELECT 
    c.id,
    c.first_name,
    c.last_name,
    c.email,
    SUM(p.amount) as total_spent,
    COUNT(b.id) as bookings_count
FROM clients c
LEFT JOIN bookings b ON b.client_id = c.id
LEFT JOIN payments p ON p.booking_id = b.id
WHERE p.status = 'paid'
GROUP BY c.id
ORDER BY total_spent DESC
LIMIT 10;
```

### Distribution géographique
```sql
SELECT 
    city,
    COUNT(*) as client_count
FROM clients
WHERE city IS NOT NULL
GROUP BY city
ORDER BY client_count DESC
LIMIT 10;
```

---

## 🚀 Optimisations

✅ **Pagination** pour éviter de charger tous les clients  
✅ **Eager loading** des relations (QueryBuilder + leftJoin)  
✅ **Calculs en SQL** (SUM, COUNT) plutôt qu'en PHP  
✅ **Limites** sur toutes les listes (setMaxResults)  
✅ **Index BDD** sur email, isActive, isVerified  

---

## ⚙️ Installation requise

```bash
# Paginator
composer require knplabs/knp-paginator-bundle

# Stripe (si pas déjà installé)
composer require stripe/stripe-php
```

---

## 📝 TODO Liste

- [ ] Implémenter envoi emails (Symfony Mailer)
- [ ] Ajouter logs des actions admin
- [ ] Export PDF des statistiques
- [ ] Graphiques interactifs (Chart.js)
- [ ] Système de notes admin privées
- [ ] Historique des modifications
- [ ] Notifications push

---

## 🔗 Routes associées

```yaml
admin_clients                    GET    /admin/clients
admin_client_show                GET    /admin/clients/{id}
admin_client_activate            POST   /admin/clients/{id}/activate
admin_client_deactivate          POST   /admin/clients/{id}/deactivate
admin_client_verify              POST   /admin/clients/{id}/verify
admin_client_delete              POST   /admin/clients/{id}/delete
admin_client_edit                GET/POST /admin/clients/{id}/edit
admin_client_transactions        GET    /admin/clients/{id}/transactions
admin_client_stripe_create       POST   /admin/clients/{id}/stripe/create
admin_clients_stats              GET    /admin/clients/stats/global
admin_clients_export             GET    /admin/clients/export/csv
admin_client_notify              POST   /admin/clients/{id}/notify
admin_client_reset_password      POST   /admin/clients/{id}/reset-password
admin_client_activity            GET    /admin/clients/{id}/activity
```

---

**Date de création**: 2024-01-30  
**Version**: 1.0.0  
**Auteur**: Admin Panel System