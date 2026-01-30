Création du template admin/dashboard.html.twig avec Bootstrap 5 et un design professionnel moderne
admin_dashboard.html.twig
Maintenant créons le template de base :


Création du template base.html.twig avec Bootstrap 5
base.html.twig

Presented 2 files
✅ Dashboard Admin professionnel créé !
J'ai créé 2 templates Twig avec Bootstrap 5 et un design moderne :

📄 1. base.html.twig - Template de base
Inclusions :

✅ Bootstrap 5.3.2
✅ Font Awesome 6.4.2
✅ Google Fonts (Inter)
✅ jQuery 3.7.1
Fonctionnalités JS :

Auto-fermeture des alertes (5s)
Confirmation des suppressions
Tooltips & Popovers Bootstrap
Fonction showNotification(message, type)
Helper AJAX fetchJSON(url, options)
Styles globaux :

Scrollbar personnalisée
Animations fade-in
Classes utilitaires (text-gradient, shadow-sm-hover)
🎨 2. admin/dashboard.html.twig - Dashboard admin
Layout professionnel avec :

Sidebar fixe (250px) :
- Logo/Brand en haut
- Navigation avec icônes
- Gradient moderne (2c3e50 → 34495e)
- Effets hover avec translation
- Responsive (collapse sur mobile)
Menu navigation :

📊 Dashboard
👥 Utilisateurs
💼 Prestataires
👤 Clients
📅 Réservations
💳 Paiements
🏷️ Catégories
⭐ Avis
📈 Rapports
⚙️ Paramètres
Topbar sticky (60px) :
- Toggle sidebar (mobile)
- Titre de la page
- Notifications (badge avec compteur)
- Menu utilisateur avec avatar
4 Cards statistiques avec gradients :
Primary (Violet) :

php
- Total Utilisateurs: 1234
- +12% ce mois ↑
- Gradient: 667eea → 764ba2
Success (Vert) :

php
- Réservations: 567
- +8% ce mois ↑
- Gradient: 11998e → 38ef7d
Warning (Rose) :

php
- Revenus: 45.2K€
- +15% ce mois ↑
- Gradient: f093fb → f5576c
Info (Bleu) :

php
- Prestataires: 89
- -2% ce mois ↓
- Gradient: 4facfe → 00f2fe
2 Graphiques Chart.js :
1. Line Chart - Réservations mensuelles :

javascript
- 12 mois de données
- Gradient fill
- Animation fluide
- Responsive
2. Doughnut Chart - Catégories populaires :

javascript
- 5 catégories principales
- Couleurs gradient
- Légende en bas
```

#### **Tableau des dernières réservations :**
```
Colonnes:
- ID
- Client
- Prestataire
- Service
- Date
- Montant
- Statut (badge coloré)
```

#### **Activité récente (timeline) :**
```
- Événements en temps réel
- Bordure gauche colorée
- Timestamp relatif
- Auto-scroll
🎯 Variables Twig à passer depuis le contrôleur
php
// src/Controller/Admin/DashboardController.php

#[Route('/admin/dashboard', name: 'admin_dashboard')]
public function index(): Response
{
    return $this->render('admin/dashboard.html.twig', [
        'stats' => [
            'totalUsers' => 1234,
            'totalBookings' => 567,
            'totalRevenue' => '45.2K',
            'totalPrestataires' => 89,
        ],
        'recentBookings' => [
            [
                'id' => 1234,
                'client' => ['fullName' => 'Jean Dupont'],
                'prestataire' => ['fullName' => 'Marie Martin'],
                'service' => 'Ménage',
                'date' => new \DateTime(),
                'amount' => 50,
                'status' => 'Confirmée',
                'statusColor' => 'success',
            ],
            // ...
        ],
        'recentActivities' => [
            [
                'title' => 'Nouveau utilisateur',
                'description' => 'Jean Dupont s\'est inscrit',
                'time' => 'Il y a 5 min',
            ],
            // ...
        ],
    ]);
}
🎨 Design moderne avec :
✅ Gradient cards avec effets hover ✅ Animations smooth sur tous les éléments ✅ Icons Font Awesome pour navigation ✅ Charts.js pour visualisations ✅ Responsive (mobile, tablet, desktop) ✅ Notifications système en haut à droite ✅ Dropdown menus Bootstrap ✅ Badges colorés pour statuts ✅ Shadow effects au hover ✅ Color scheme professionnel (bleu/violet/vert)

📱 Responsive breakpoints :
css
Desktop (>768px) : Sidebar visible
Tablet/Mobile (<768px) : Sidebar cachée (toggle button)
🚀 Routes nécessaires dans votre routing :
yaml
# config/routes.yaml

admin_dashboard:
    path: /admin/dashboard
    controller: App\Controller\Admin\DashboardController::index

admin_users:
    path: /admin/users

admin_prestataires:
    path: /admin/prestataires

admin_clients:
    path: /admin/clients

admin_bookings:
    path: /admin/bookings

admin_payments:
    path: /admin/payments

admin_categories:
    path: /admin/categories

admin_reviews:
    path: /admin/reviews

admin_reports:
    path: /admin/reports

admin_settings:
    path: /admin/settings

admin_profile:
    path: /admin/profile

app_logout:
    path: /logout
Le dashboard est prêt à l'emploi et entièrement personnalisable ! 🎨✨