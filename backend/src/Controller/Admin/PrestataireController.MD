✅ PrestataireController Admin créé !
Voici le contrôleur complet pour gérer les prestataires avec 15 actions :
📋 Actions principales
1. index() - Liste des prestataires
phpGET /admin/prestataires

Fonctionnalités :
✅ Pagination (20 par page)
✅ Filtres par statut (pending/approved/inactive)
✅ Recherche (nom, email, entreprise, SIRET)
✅ Statistiques globales (total, en attente, approuvés, inactifs)
2. show($id) - Détails d'un prestataire
phpGET /admin/prestataires/{id}

Affiche :
✅ Infos complètes du prestataire
✅ Stats (réservations, taux complétion, revenus)
✅ 10 dernières réservations
✅ Statut compte Stripe Connect
3. approve($id) - Approuver un prestataire
phpPOST /admin/prestataires/{id}/approve

Actions :
✅ Marque isApproved = true
✅ Enregistre approvedAt
✅ Flash message succès
✅ TODO: Envoyer email confirmation
4. unapprove($id) - Désapprouver
phpPOST /admin/prestataires/{id}/unapprove

Paramètres :
- reason: Raison du refus

Actions :
✅ Marque isApproved = false
✅ approvedAt = null
✅ TODO: Envoyer email avec raison
5. activate($id) - Activer
phpPOST /admin/prestataires/{id}/activate

✅ isActive = true
6. deactivate($id) - Désactiver
phpPOST /admin/prestataires/{id}/deactivate

Paramètres :
- reason: Raison de la désactivation

✅ isActive = false
7. delete($id) - Supprimer
phpPOST /admin/prestataires/{id}/delete

Sécurité :
✅ Vérifie qu'il n'a pas de réservations actives
✅ Bloque si réservations en cours
✅ Suppression si OK
8. edit($id) - Modifier
phpGET/POST /admin/prestataires/{id}/edit

Champs modifiables :
✅ firstName, lastName, email, phone
✅ companyName, siret
✅ address, city, postalCode
✅ hourlyRate

💳 Actions Stripe Connect
9. stripeStatus($id) - Vérifier statut
phpGET /admin/prestataires/{id}/stripe/status

Retour JSON :
{
    "success": true,
    "status": {
        "id": "acct_xxx",
        "charges_enabled": true,
        "payouts_enabled": true,
        "details_submitted": true,
        "requirements": {...}
    }
}
10. createStripeAccount($id) - Créer compte
phpPOST /admin/prestataires/{id}/stripe/create

✅ Crée compte Stripe Connect Express
✅ Enregistre l'ID dans la BDD
✅ Flash message confirmation

📊 Statistiques et Exports
11. stats() - Statistiques globales
phpGET /admin/prestataires/stats/global

Données :
✅ Total, approuvés, en attente, actifs, inactifs
✅ Taux d'approbation
✅ Répartition par mois (12 mois)
✅ Top 10 prestataires (par réservations)
12. export() - Export CSV
phpGET /admin/prestataires/export/csv

Colonnes :
ID, Prénom, Nom, Email, Téléphone, Entreprise, 
SIRET, Taux horaire, Approuvé, Actif, Note moyenne, 
Nombre avis, Date inscription

Nom fichier : prestataires_2024-01-30.csv

🔔 Communication
13. notify($id) - Envoyer notification
phpPOST /admin/prestataires/{id}/notify

Paramètres :
- subject: Sujet du message
- message: Contenu du message

✅ Validation des champs
✅ TODO: Implémenter envoi email
✅ Retour JSON

📈 Statistiques calculées dans show()
php$stats = [
    'bookingsCount' => 45,          // Total réservations
    'bookingsCompleted' => 38,      // Terminées
    'completionRate' => 84.4,       // Taux de complétion (%)
    'totalRevenue' => '2340.50',    // CA total
];

// Calcul du taux de complétion
$completionRate = $bookingsCount > 0 
    ? round(($bookingsCompleted / $bookingsCount) * 100, 1) 
    : 0;

// Calcul du revenu total
$totalRevenue = SUM(payments.amount) 
WHERE booking.prestataire = X AND payment.status = 'paid'

🎯 Filtres dans index()
Par statut :
php?status=pending    → isApproved = false
?status=approved   → isApproved = true
?status=inactive   → isActive = false
Par recherche :
php?search=Dupont

Recherche dans :
- firstName
- lastName
- email
- companyName
- siret
Pagination :
php?page=2    // Page 2
// 20 résultats par page

🔒 Sécurité
php#[Route('/admin/prestataires')]
#[IsGranted('ROLE_ADMIN')]

// Toutes les routes protégées
// Seuls admins peuvent accéder
Suppression sécurisée :
php// Vérifie réservations actives avant suppression
$activeBookings = count([
    'status' => ['pending', 'confirmed', 'in_progress']
]);

if ($activeBookings > 0) {
    // Bloque la suppression
    $this->addFlash('error', 'Impossible...');
}

📦 Dépendances
php✅ EntityManagerInterface
✅ PrestataireRepository
✅ BookingRepository
✅ PaymentRepository
✅ StripeService
✅ PaginatorInterface (knplabs/knp-paginator-bundle)

💡 Installation Paginator
bashcomposer require knplabs/knp-paginator-bundle

🎨 Exemples d'utilisation dans les vues
Liste avec filtres :
twig{# admin/prestataire/index.html.twig #}

<div class="filters">
    <a href="?status=pending" class="btn">
        En attente ({{ stats.pending }})
    </a>
    <a href="?status=approved" class="btn">
        Approuvés ({{ stats.approved }})
    </a>
</div>

<form method="get">
    <input type="text" name="search" value="{{ search }}" 
           placeholder="Rechercher...">
</form>

<table>
    {% for prestataire in prestataires %}
    <tr>
        <td>{{ prestataire.fullName }}</td>
        <td>{{ prestataire.companyName }}</td>
        <td>
            {% if prestataire.isApproved %}
                <span class="badge bg-success">Approuvé</span>
            {% else %}
                <span class="badge bg-warning">En attente</span>
            {% endif %}
        </td>
        <td>
            <a href="{{ path('admin_prestataire_show', {id: prestataire.id}) }}">
                Voir
            </a>
        </td>
    </tr>
    {% endfor %}
</table>

{{ knp_pagination_render(prestataires) }}
Détails avec actions :
twig{# admin/prestataire/show.html.twig #}

<h1>{{ prestataire.fullName }}</h1>

<div class="stats">
    <div class="stat-card">
        <h3>{{ stats.bookingsCount }}</h3>
        <p>Réservations</p>
    </div>
    <div class="stat-card">
        <h3>{{ stats.completionRate }}%</h3>
        <p>Taux de complétion</p>
    </div>
    <div class="stat-card">
        <h3>{{ stats.totalRevenue }}€</h3>
        <p>Revenus totaux</p>
    </div>
</div>

<div class="actions">
    {% if not prestataire.isApproved %}
        <form method="post" action="{{ path('admin_prestataire_approve', {id: prestataire.id}) }}">
            <button class="btn btn-success">Approuver</button>
        </form>
    {% endif %}
    
    <a href="{{ path('admin_prestataire_edit', {id: prestataire.id}) }}" 
       class="btn btn-primary">Modifier</a>
</div>
Le contrôleur est prêt pour gérer tous les aspects des prestataires ! 🚀