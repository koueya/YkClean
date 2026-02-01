# Schéma de Base de Données - Plateforme de Services à Domicile

## Vue d'ensemble

Cette architecture de base de données intègre le **module Financial autonome** tout en maintenant une séparation claire entre les différents domaines métier. Le schéma utilise PostgreSQL/MySQL et Doctrine ORM.

---

## Tables Principales

### 📊 Statistiques Globales

- **Tables principales** : 35+
- **Tables du module Financial** : 10
- **Index** : ~80
- **Relations** : ~60

---

## 1. Module Utilisateurs (Users)

### Table: `users`
**Description**: Table de base pour tous les utilisateurs (héritage Single Table Inheritance)

```sql
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    dtype VARCHAR(255) NOT NULL,              -- Discriminateur: 'client', 'prestataire', 'admin'
    email VARCHAR(180) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    roles JSON NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    city VARCHAR(100),
    postal_code VARCHAR(10),
    country VARCHAR(100) DEFAULT 'France',
    is_verified BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    email_verified_at DATETIME,
    created_at DATETIME NOT NULL,
    updated_at DATETIME,
    
    -- Champs spécifiques Client
    preferred_payment_method VARCHAR(50),
    default_address TEXT,
    
    -- Champs spécifiques Prestataire
    siret VARCHAR(14),
    kbis_document VARCHAR(255),
    insurance_document VARCHAR(255),
    insurance_expiry_date DATE,
    hourly_rate DECIMAL(10,2),
    radius INT,                                -- Rayon d'intervention en km
    average_rating DECIMAL(3,2) DEFAULT 0,
    total_reviews INT DEFAULT 0,
    is_approved BOOLEAN DEFAULT FALSE,
    approved_at DATETIME,
    approved_by INT,
    bio TEXT,
    experience_years INT,
    
    INDEX idx_dtype (dtype),
    INDEX idx_email (email),
    INDEX idx_is_approved (is_approved),
    INDEX idx_created_at (created_at),
    
    CONSTRAINT fk_approved_by FOREIGN KEY (approved_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Relations**:
- OneToMany → `service_requests` (Client)
- OneToMany → `bookings` (Client et Prestataire)
- OneToMany → `quotes` (Prestataire)
- OneToMany → `availabilities` (Prestataire)
- OneToMany → `reviews` (Client)
- OneToMany → `financial_prestataire_earning` (Prestataire)
- OneToMany → `financial_payout` (Prestataire)

---

## 2. Module Services

### Table: `service_categories`
```sql
CREATE TABLE service_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    icon VARCHAR(100),
    is_active BOOLEAN DEFAULT TRUE,
    display_order INT DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME,
    
    INDEX idx_slug (slug),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Table: `service_types`
```sql
CREATE TABLE service_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    description TEXT,
    estimated_duration INT,                    -- en minutes
    base_price DECIMAL(10,2),
    is_active BOOLEAN DEFAULT TRUE,
    created_at DATETIME NOT NULL,
    
    INDEX idx_category (category_id),
    INDEX idx_slug (slug),
    
    CONSTRAINT fk_service_type_category FOREIGN KEY (category_id) 
        REFERENCES service_categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Table: `prestataire_service_categories`
**Description**: Table pivot pour les catégories de services proposées par les prestataires

```sql
CREATE TABLE prestataire_service_categories (
    prestataire_id INT NOT NULL,
    service_category_id INT NOT NULL,
    
    PRIMARY KEY (prestataire_id, service_category_id),
    
    CONSTRAINT fk_psc_prestataire FOREIGN KEY (prestataire_id) 
        REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_psc_category FOREIGN KEY (service_category_id) 
        REFERENCES service_categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## 3. Module Demandes de Service

### Table: `service_requests`
```sql
CREATE TABLE service_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    category_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    address TEXT NOT NULL,
    city VARCHAR(100),
    postal_code VARCHAR(10),
    latitude DECIMAL(10,8),
    longitude DECIMAL(11,8),
    preferred_date DATETIME,
    alternative_dates JSON,                    -- Tableau de dates alternatives
    duration INT,                              -- Durée estimée en minutes
    frequency VARCHAR(50),                     -- 'once', 'weekly', 'biweekly', 'monthly'
    budget DECIMAL(10,2),
    status VARCHAR(50) DEFAULT 'open',         -- 'open', 'quoted', 'in_progress', 'completed', 'cancelled'
    created_at DATETIME NOT NULL,
    updated_at DATETIME,
    expires_at DATETIME,
    
    INDEX idx_client (client_id),
    INDEX idx_category (category_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),
    INDEX idx_location (latitude, longitude),
    
    CONSTRAINT fk_sr_client FOREIGN KEY (client_id) 
        REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_sr_category FOREIGN KEY (category_id) 
        REFERENCES service_categories(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Relations**:
- ManyToOne → `users` (Client)
- ManyToOne → `service_categories`
- OneToMany → `quotes`

---

## 4. Module Devis (Quotes)

### Table: `quotes`
```sql
CREATE TABLE quotes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    service_request_id INT NOT NULL,
    prestataire_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    proposed_date DATETIME NOT NULL,
    proposed_duration INT NOT NULL,            -- en minutes
    description TEXT,
    conditions TEXT,
    status VARCHAR(50) DEFAULT 'pending',      -- 'pending', 'accepted', 'rejected', 'expired'
    valid_until DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME,
    accepted_at DATETIME,
    rejected_at DATETIME,
    rejection_reason TEXT,
    
    INDEX idx_service_request (service_request_id),
    INDEX idx_prestataire (prestataire_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),
    
    CONSTRAINT fk_quote_sr FOREIGN KEY (service_request_id) 
        REFERENCES service_requests(id) ON DELETE CASCADE,
    CONSTRAINT fk_quote_prestataire FOREIGN KEY (prestataire_id) 
        REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```
**Relations**:
- ManyToOne → `service_requests`
- ManyToOne → `users` (Prestataire)
- OneToOne → `bookings`


### Table: `quote_items`
```sql
CREATE TABLE quote_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quote_id INT NOT NULL,
    service_type_id INT,
    description VARCHAR(255) NOT NULL,
    quantity INT NOT NULL,
    unit VARCHAR(50),
    unit_price DECIMAL(10,2) NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    estimated_duration INT,
    notes TEXT,
    options JSON,
    is_optional BOOLEAN DEFAULT FALSE,
    display_order INT DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME,
    
    FOREIGN KEY (quote_id) REFERENCES quotes(id) ON DELETE CASCADE,
    FOREIGN KEY (service_type_id) REFERENCES service_types(id) ON DELETE SET NULL
);
```
**Relations** :
- `ManyToOne` → `Quote` (un item appartient à un devis)
- `ManyToOne` → `ServiceType` (optionnel - lien vers type de service)

---

## 5. Module Réservations (Bookings)

### Table: `bookings`
```sql
CREATE TABLE bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quote_id INT UNIQUE,
    client_id INT NOT NULL,
    prestataire_id INT NOT NULL,
    service_category_id INT NOT NULL,
    recurrence_id INT,                         -- NULL si réservation unique
    
    scheduled_date DATE NOT NULL,
    scheduled_time TIME NOT NULL,
    duration INT NOT NULL,                     -- en minutes
    
    address TEXT NOT NULL,
    city VARCHAR(100),
    postal_code VARCHAR(10),
    
    amount DECIMAL(10,2) NOT NULL,
    status VARCHAR(50) DEFAULT 'scheduled',    -- 'scheduled', 'confirmed', 'in_progress', 'completed', 'cancelled'
    
    actual_start_time DATETIME,
    actual_end_time DATETIME,
    completion_notes TEXT,
    
    cancelled_at DATETIME,
    cancellation_reason TEXT,
    cancelled_by INT,                          -- user_id qui a annulé
    
    created_at DATETIME NOT NULL,
    updated_at DATETIME,
    
    INDEX idx_quote (quote_id),
    INDEX idx_client (client_id),
    INDEX idx_prestataire (prestataire_id),
    INDEX idx_status (status),
    INDEX idx_scheduled_date (scheduled_date),
    INDEX idx_created_at (created_at),
    
    CONSTRAINT fk_booking_quote FOREIGN KEY (quote_id) 
        REFERENCES quotes(id) ON DELETE SET NULL,
    CONSTRAINT fk_booking_client FOREIGN KEY (client_id) 
        REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_booking_prestataire FOREIGN KEY (prestataire_id) 
        REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_booking_category FOREIGN KEY (service_category_id) 
        REFERENCES service_categories(id),
    CONSTRAINT fk_booking_recurrence FOREIGN KEY (recurrence_id) 
        REFERENCES recurrences(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Relations**:
- OneToOne → `quotes`
- ManyToOne → `users` (Client)
- ManyToOne → `users` (Prestataire)
- ManyToOne → `service_categories`
- ManyToOne → `recurrences`
- OneToOne → `financial_payment`
- OneToOne → `financial_commission`
- OneToOne → `financial_prestataire_earning`

### Table: `recurrences`
```sql
CREATE TABLE recurrences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    frequency VARCHAR(50) NOT NULL,            -- 'weekly', 'biweekly', 'monthly'
    interval_value INT NOT NULL DEFAULT 1,
    day_of_week INT,                           -- 0=Dimanche, 6=Samedi
    day_of_month INT,
    start_date DATE NOT NULL,
    end_date DATE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at DATETIME NOT NULL,
    
    INDEX idx_frequency (frequency),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## 6. Module Planning

### Table: `availabilities`
```sql
CREATE TABLE availabilities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    prestataire_id INT NOT NULL,
    day_of_week INT,                           -- 0-6 (NULL si date spécifique)
    specific_date DATE,                        -- NULL si récurrent
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    is_recurring BOOLEAN DEFAULT TRUE,
    is_available BOOLEAN DEFAULT TRUE,
    notes TEXT,
    created_at DATETIME NOT NULL,
    updated_at DATETIME,
    
    INDEX idx_prestataire (prestataire_id),
    INDEX idx_day_of_week (day_of_week),
    INDEX idx_specific_date (specific_date),
    
    CONSTRAINT fk_availability_prestataire FOREIGN KEY (prestataire_id) 
        REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Table: `absences`
```sql
CREATE TABLE absences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    prestataire_id INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    reason VARCHAR(255),
    status VARCHAR(50) DEFAULT 'pending',      -- 'pending', 'approved', 'rejected'
    requires_replacement BOOLEAN DEFAULT FALSE,
    created_at DATETIME NOT NULL,
    updated_at DATETIME,
    
    INDEX idx_prestataire (prestataire_id),
    INDEX idx_dates (start_date, end_date),
    INDEX idx_status (status),
    
    CONSTRAINT fk_absence_prestataire FOREIGN KEY (prestataire_id) 
        REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Table: `replacements`
```sql
CREATE TABLE replacements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    original_booking_id INT NOT NULL,
    original_prestataire_id INT NOT NULL,
    replacement_prestataire_id INT,
    reason TEXT,
    status VARCHAR(50) DEFAULT 'pending',      -- 'pending', 'confirmed', 'rejected'
    requested_at DATETIME NOT NULL,
    confirmed_at DATETIME,
    
    INDEX idx_original_booking (original_booking_id),
    INDEX idx_original_prestataire (original_prestataire_id),
    INDEX idx_replacement_prestataire (replacement_prestataire_id),
    INDEX idx_status (status),
    
    CONSTRAINT fk_replacement_booking FOREIGN KEY (original_booking_id) 
        REFERENCES bookings(id) ON DELETE CASCADE,
    CONSTRAINT fk_replacement_original FOREIGN KEY (original_prestataire_id) 
        REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_replacement_new FOREIGN KEY (replacement_prestataire_id) 
        REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## 7. Module Avis (Reviews)

### Table: `reviews`
```sql
CREATE TABLE reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT UNIQUE NOT NULL,
    client_id INT NOT NULL,
    prestataire_id INT NOT NULL,
    rating INT NOT NULL,                       -- 1-5
    comment TEXT,
    quality_rating INT,                        -- 1-5
    punctuality_rating INT,                    -- 1-5
    professionalism_rating INT,                -- 1-5
    is_verified BOOLEAN DEFAULT FALSE,
    is_visible BOOLEAN DEFAULT TRUE,
    created_at DATETIME NOT NULL,
    updated_at DATETIME,
    
    INDEX idx_booking (booking_id),
    INDEX idx_client (client_id),
    INDEX idx_prestataire (prestataire_id),
    INDEX idx_rating (rating),
    INDEX idx_created_at (created_at),
    
    CONSTRAINT fk_review_booking FOREIGN KEY (booking_id) 
        REFERENCES bookings(id) ON DELETE CASCADE,
    CONSTRAINT fk_review_client FOREIGN KEY (client_id) 
        REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_review_prestataire FOREIGN KEY (prestataire_id) 
        REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT chk_rating CHECK (rating BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## 8. Module Notifications

### Table: `notifications`
```sql
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,                 -- 'email', 'sms', 'push', 'in_app'
    channel VARCHAR(50) NOT NULL,
    subject VARCHAR(255),
    message TEXT NOT NULL,
    data JSON,                                 -- Données supplémentaires
    is_read BOOLEAN DEFAULT FALSE,
    read_at DATETIME,
    sent_at DATETIME,
    created_at DATETIME NOT NULL,
    
    INDEX idx_user (user_id),
    INDEX idx_type (type),
    INDEX idx_is_read (is_read),
    INDEX idx_created_at (created_at),
    
    CONSTRAINT fk_notification_user FOREIGN KEY (user_id) 
        REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## 9. MODULE FINANCIAL (Autonome) 🏦

### Table: `financial_payment`
**Description**: Paiements effectués par les clients

```sql
CREATE TABLE financial_payment (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT UNIQUE NOT NULL,
    client_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'EUR',
    payment_method VARCHAR(50) NOT NULL,       -- 'card', 'bank_transfer', 'stripe', 'mangopay'
    payment_gateway VARCHAR(50),               -- 'stripe', 'mangopay'
    gateway_transaction_id VARCHAR(255) UNIQUE,
    status VARCHAR(50) DEFAULT 'pending',      -- 'pending', 'processing', 'completed', 'failed', 'refunded'
    metadata JSON,
    
    paid_at DATETIME,
    failed_at DATETIME,
    failure_reason TEXT,
    
    created_at DATETIME NOT NULL,
    updated_at DATETIME,
    
    INDEX idx_booking (booking_id),
    INDEX idx_client (client_id),
    INDEX idx_status (status),
    INDEX idx_gateway_transaction (gateway_transaction_id),
    INDEX idx_created_at (created_at),
    
    CONSTRAINT fk_payment_booking FOREIGN KEY (booking_id) 
        REFERENCES bookings(id) ON DELETE CASCADE,
    CONSTRAINT fk_payment_client FOREIGN KEY (client_id) 
        REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Table: `financial_commission`
**Description**: Commissions prélevées par la plateforme

```sql
CREATE TABLE financial_commission (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT UNIQUE NOT NULL,
    payment_id INT NOT NULL,
    commission_rule_id INT,
    
    base_amount DECIMAL(10,2) NOT NULL,        -- Montant de base (du service)
    commission_rate DECIMAL(5,2) NOT NULL,     -- Taux en pourcentage
    commission_amount DECIMAL(10,2) NOT NULL,  -- Montant de la commission
    
    calculation_method VARCHAR(50),            -- 'percentage', 'fixed', 'tiered'
    calculation_details JSON,
    
    created_at DATETIME NOT NULL,
    
    INDEX idx_booking (booking_id),
    INDEX idx_payment (payment_id),
    INDEX idx_commission_rule (commission_rule_id),
    INDEX idx_created_at (created_at),
    
    CONSTRAINT fk_commission_booking FOREIGN KEY (booking_id) 
        REFERENCES bookings(id) ON DELETE CASCADE,
    CONSTRAINT fk_commission_payment FOREIGN KEY (payment_id) 
        REFERENCES financial_payment(id) ON DELETE CASCADE,
    CONSTRAINT fk_commission_rule FOREIGN KEY (commission_rule_id) 
        REFERENCES financial_commission_rule(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Table: `financial_prestataire_earning`
**Description**: Gains des prestataires (montant net après commission)

```sql
CREATE TABLE financial_prestataire_earning (
    id INT AUTO_INCREMENT PRIMARY KEY,
    prestataire_id INT NOT NULL,
    booking_id INT UNIQUE NOT NULL,
    
    total_amount DECIMAL(10,2) NOT NULL,       -- Montant total du service
    commission_amount DECIMAL(10,2) NOT NULL,  -- Commission prélevée
    net_amount DECIMAL(10,2) NOT NULL,         -- Montant net pour le prestataire
    
    payout_id INT,                             -- NULL si pas encore versé
    
    status VARCHAR(50) DEFAULT 'pending',      -- 'pending', 'available', 'paid', 'disputed', 'cancelled'
    
    earned_at DATETIME NOT NULL,
    available_at DATETIME,                     -- Date à partir de laquelle le gain est disponible
    paid_at DATETIME,
    
    notes TEXT,
    
    created_at DATETIME NOT NULL,
    updated_at DATETIME,
    
    INDEX idx_prestataire_status (prestataire_id, status),
    INDEX idx_booking (booking_id),
    INDEX idx_payout (payout_id),
    INDEX idx_status (status),
    INDEX idx_earned_at (earned_at),
    INDEX idx_available_at (available_at),
    
    CONSTRAINT fk_earning_prestataire FOREIGN KEY (prestataire_id) 
        REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_earning_booking FOREIGN KEY (booking_id) 
        REFERENCES bookings(id) ON DELETE CASCADE,
    CONSTRAINT fk_earning_payout FOREIGN KEY (payout_id) 
        REFERENCES financial_payout(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Table: `financial_payout`
**Description**: Versements groupés aux prestataires

```sql
CREATE TABLE financial_payout (
    id INT AUTO_INCREMENT PRIMARY KEY,
    prestataire_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    
    status VARCHAR(50) DEFAULT 'pending',      -- 'pending', 'approved', 'processing', 'completed', 'failed', 'rejected'
    
    payment_method VARCHAR(50) NOT NULL,       -- 'bank_transfer', 'stripe_payout', 'mangopay_payout'
    bank_details JSON,                         -- IBAN, BIC, etc.
    
    transaction_reference VARCHAR(100) UNIQUE,
    failure_reason TEXT,
    
    requested_at DATETIME NOT NULL,
    approved_at DATETIME,
    processed_at DATETIME,
    completed_at DATETIME,
    
    approved_by INT,                           -- Admin qui a approuvé
    
    created_at DATETIME NOT NULL,
    updated_at DATETIME,
    
    INDEX idx_prestataire_status (prestataire_id, status),
    INDEX idx_status (status),
    INDEX idx_transaction_ref (transaction_reference),
    INDEX idx_requested_at (requested_at),
    
    CONSTRAINT fk_payout_prestataire FOREIGN KEY (prestataire_id) 
        REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_payout_approved_by FOREIGN KEY (approved_by) 
        REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Table: `financial_transaction`
**Description**: Journal de toutes les transactions financières (traçabilité complète)

```sql
CREATE TABLE financial_transaction (
    id INT AUTO_INCREMENT PRIMARY KEY,
    
    type VARCHAR(30) NOT NULL,                 -- 'payment', 'commission', 'earning', 'payout', 'refund', 'adjustment'
    
    user_id INT,
    booking_id INT,
    payment_id INT,
    commission_id INT,
    earning_id INT,
    payout_id INT,
    
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'EUR',
    status VARCHAR(20) NOT NULL,
    
    metadata JSON,                             -- Données supplémentaires
    reference VARCHAR(100) UNIQUE NOT NULL,    -- Référence unique (ex: TXN-ABC123)
    
    created_at DATETIME NOT NULL,
    
    INDEX idx_type_created (type, created_at),
    INDEX idx_user_created (user_id, created_at),
    INDEX idx_reference (reference),
    INDEX idx_booking (booking_id),
    INDEX idx_status (status),
    
    CONSTRAINT fk_transaction_user FOREIGN KEY (user_id) 
        REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_transaction_booking FOREIGN KEY (booking_id) 
        REFERENCES bookings(id) ON DELETE SET NULL,
    CONSTRAINT fk_transaction_payment FOREIGN KEY (payment_id) 
        REFERENCES financial_payment(id) ON DELETE SET NULL,
    CONSTRAINT fk_transaction_commission FOREIGN KEY (commission_id) 
        REFERENCES financial_commission(id) ON DELETE SET NULL,
    CONSTRAINT fk_transaction_earning FOREIGN KEY (earning_id) 
        REFERENCES financial_prestataire_earning(id) ON DELETE SET NULL,
    CONSTRAINT fk_transaction_payout FOREIGN KEY (payout_id) 
        REFERENCES financial_payout(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Table: `financial_commission_rule`
**Description**: Règles de calcul des commissions

```sql
CREATE TABLE financial_commission_rule (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    
    type VARCHAR(20) NOT NULL,                 -- 'percentage', 'fixed', 'tiered'
    value DECIMAL(5,2) NOT NULL,               -- Pourcentage ou montant fixe
    
    min_amount DECIMAL(10,2),                  -- Seuil minimum
    max_amount DECIMAL(10,2),                  -- Seuil maximum
    
    service_category_id INT,                   -- NULL = règle globale
    
    conditions JSON,                           -- Conditions supplémentaires
    
    is_active BOOLEAN DEFAULT TRUE,
    priority INT DEFAULT 0,                    -- Ordre d'application
    
    valid_from DATETIME NOT NULL,
    valid_until DATETIME,
    
    created_at DATETIME NOT NULL,
    updated_at DATETIME,
    
    INDEX idx_type (type),
    INDEX idx_is_active (is_active),
    INDEX idx_priority (priority),
    INDEX idx_category (service_category_id),
    
    CONSTRAINT fk_commission_rule_category FOREIGN KEY (service_category_id) 
        REFERENCES service_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Table: `financial_invoice`
**Description**: Factures générées

```sql
CREATE TABLE financial_invoice (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_number VARCHAR(50) UNIQUE NOT NULL,
    
    type VARCHAR(50) DEFAULT 'standard',       -- 'standard', 'advance', 'credit_note', 'proforma'
    
    client_id INT NOT NULL,
    prestataire_id INT NOT NULL,
    booking_id INT,
    payment_id INT,
    
    amount DECIMAL(10,2) NOT NULL,
    tax_amount DECIMAL(10,2) DEFAULT 0,
    total_amount DECIMAL(10,2) NOT NULL,
    
    status VARCHAR(50) DEFAULT 'draft',        -- 'draft', 'sent', 'paid', 'overdue', 'cancelled'
    
    issue_date DATE NOT NULL,
    due_date DATE,
    paid_date DATE,
    
    pdf_path VARCHAR(255),
    
    created_at DATETIME NOT NULL,
    updated_at DATETIME,
    
    INDEX idx_invoice_number (invoice_number),
    INDEX idx_client_status (client_id, status),
    INDEX idx_prestataire_status (prestataire_id, status),
    INDEX idx_status_due (status, due_date),
    INDEX idx_created_at (created_at),
    
    CONSTRAINT fk_invoice_client FOREIGN KEY (client_id) 
        REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_invoice_prestataire FOREIGN KEY (prestataire_id) 
        REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_invoice_booking FOREIGN KEY (booking_id) 
        REFERENCES bookings(id) ON DELETE SET NULL,
    CONSTRAINT fk_invoice_payment FOREIGN KEY (payment_id) 
        REFERENCES financial_payment(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Table: `financial_refund_request`
**Description**: Demandes de remboursement

```sql
CREATE TABLE financial_refund_request (
    id INT AUTO_INCREMENT PRIMARY KEY,
    payment_id INT NOT NULL,
    client_id INT NOT NULL,
    
    amount DECIMAL(10,2) NOT NULL,
    reason TEXT NOT NULL,
    
    status VARCHAR(50) DEFAULT 'requested',    -- 'requested', 'approved', 'processing', 'completed', 'rejected'
    
    requested_at DATETIME NOT NULL,
    approved_at DATETIME,
    processed_at DATETIME,
    completed_at DATETIME,
    
    admin_notes TEXT,
    rejection_reason TEXT,
    
    approved_by INT,
    
    created_at DATETIME NOT NULL,
    updated_at DATETIME,
    
    INDEX idx_payment (payment_id),
    INDEX idx_client (client_id),
    INDEX idx_status (status),
    INDEX idx_requested_at (requested_at),
    
    CONSTRAINT fk_refund_payment FOREIGN KEY (payment_id) 
        REFERENCES financial_payment(id) ON DELETE CASCADE,
    CONSTRAINT fk_refund_client FOREIGN KEY (client_id) 
        REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_refund_approved_by FOREIGN KEY (approved_by) 
        REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Table: `financial_payment_method`
**Description**: Méthodes de paiement enregistrées par les clients

```sql
CREATE TABLE financial_payment_method (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    
    type VARCHAR(50) NOT NULL,                 -- 'card', 'bank_account'
    provider VARCHAR(50),                      -- 'stripe', 'mangopay'
    provider_payment_method_id VARCHAR(255),
    
    is_default BOOLEAN DEFAULT FALSE,
    
    card_last4 VARCHAR(4),
    card_brand VARCHAR(50),
    card_exp_month INT,
    card_exp_year INT,
    
    bank_name VARCHAR(100),
    bank_account_last4 VARCHAR(4),
    
    is_active BOOLEAN DEFAULT TRUE,
    
    created_at DATETIME NOT NULL,
    updated_at DATETIME,
    
    INDEX idx_client (client_id),
    INDEX idx_is_default (is_default),
    INDEX idx_provider (provider, provider_payment_method_id),
    
    CONSTRAINT fk_payment_method_client FOREIGN KEY (client_id) 
        REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## 10. Module Documents

### Table: `documents`
```sql
CREATE TABLE documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    prestataire_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,                 -- 'kbis', 'insurance', 'id_card', 'certificate'
    file_path VARCHAR(255) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_size INT,
    mime_type VARCHAR(100),
    status VARCHAR(50) DEFAULT 'pending',      -- 'pending', 'approved', 'rejected'
    expiry_date DATE,
    verified_at DATETIME,
    verified_by INT,
    rejection_reason TEXT,
    created_at DATETIME NOT NULL,
    updated_at DATETIME,
    
    INDEX idx_prestataire (prestataire_id),
    INDEX idx_type (type),
    INDEX idx_status (status),
    
    CONSTRAINT fk_document_prestataire FOREIGN KEY (prestataire_id) 
        REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_document_verified_by FOREIGN KEY (verified_by) 
        REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## Modèle Relationnel (Diagramme Textuel)

```
┌─────────────────────────────────────────────────────────────────────┐
│                       SCHÉMA RELATIONNEL COMPLET                    │
└─────────────────────────────────────────────────────────────────────┘

┌──────────────┐
│    USERS     │ (Single Table Inheritance)
├──────────────┤
│ id (PK)      │
│ dtype        │ ← Discriminateur: 'client', 'prestataire', 'admin'
│ email        │
│ ...          │
└──────┬───────┘
       │
       ├─────────────────────────────────────────────────────┐
       │                                                     │
       ↓                                                     ↓
┌──────────────────┐                              ┌──────────────────┐
│ SERVICE_REQUESTS │                              │     QUOTES       │
├──────────────────┤                              ├──────────────────┤
│ id (PK)          │                              │ id (PK)          │
│ client_id (FK)   │──────────────────────────────→│ prestataire_id   │
│ category_id (FK) │                              │ sr_id (FK)       │
└────────┬─────────┘                              └────────┬─────────┘
         │                                                 │
         │                                                 │ OneToOne
         │                                                 ↓
         │                                        ┌──────────────────┐
         │                                        │    BOOKINGS      │
         │                                        ├──────────────────┤
         │                                        │ id (PK)          │
         └────────────────────────────────────────→│ quote_id (FK)    │
                                                  │ client_id (FK)   │
                                                  │ prestataire_id   │
                                                  └────────┬─────────┘
                                                           │
                            ┌──────────────────────────────┼─────────────────────────┐
                            │                              │                         │
                            ↓                              ↓                         ↓
                  ┌──────────────────┐         ┌──────────────────┐     ┌──────────────────┐
                  │ FINANCIAL_PAYMENT│         │FINANCIAL_COMMISSION│     │ FINANCIAL_EARNING│
                  ├──────────────────┤         ├──────────────────┤     ├──────────────────┤
                  │ id (PK)          │         │ id (PK)          │     │ id (PK)          │
                  │ booking_id (FK)  │────────→│ payment_id (FK)  │     │ booking_id (FK)  │
                  │ client_id (FK)   │         │ booking_id (FK)  │     │ prestataire_id   │
                  │ amount           │         │ commission_amt   │     │ payout_id (FK)   │
                  └──────────────────┘         └──────────────────┘     └─────────┬────────┘
                                                                                   │
                                                                                   │ ManyToOne
                                                                                   ↓
                                                                        ┌──────────────────┐
                                                                        │ FINANCIAL_PAYOUT │
                                                                        ├──────────────────┤
                                                                        │ id (PK)          │
                                                                        │ prestataire_id   │
                                                                        │ amount           │
                                                                        │ status           │
                                                                        └──────────────────┘

┌─────────────────────────────────────────────────────────────────────┐
│                    TRANSACTION COMPLÈTE (Traçabilité)                │
└─────────────────────────────────────────────────────────────────────┘

                            ┌────────────────────────┐
                            │ FINANCIAL_TRANSACTION  │
                            ├────────────────────────┤
                            │ id (PK)                │
                            │ type (ENUM)            │
                            │ user_id (FK)           │
                            │ booking_id (FK)        │
                            │ payment_id (FK)        │─→ Liens vers toutes
                            │ commission_id (FK)     │  les entités financières
                            │ earning_id (FK)        │
                            │ payout_id (FK)         │
                            │ reference (UNIQUE)     │
                            │ created_at             │
                            └────────────────────────┘
```

---

## Relations Clés par Module

### Relations Utilisateurs
```
users (Client)
  ↓ OneToMany
  ├─→ service_requests
  ├─→ bookings (as client)
  ├─→ reviews
  ├─→ financial_payment
  └─→ financial_payment_method

users (Prestataire)
  ↓ OneToMany
  ├─→ quotes
  ├─→ bookings (as prestataire)
  ├─→ availabilities
  ├─→ absences
  ├─→ financial_prestataire_earning
  ├─→ financial_payout
  └─→ documents
```

### Relations Financières (Module autonome)
```
bookings
  ↓ OneToOne
  financial_payment
    ↓ OneToOne
    financial_commission
  
bookings
  ↓ OneToOne
  financial_prestataire_earning
    ↓ ManyToOne
    financial_payout

Traçabilité complète:
  → financial_transaction (enregistre TOUT)
```

---

## Index Stratégiques

### Index de Performance
```sql
-- Recherche géographique
CREATE INDEX idx_service_request_location ON service_requests(latitude, longitude);

-- Recherche de disponibilités
CREATE INDEX idx_availability_prestataire_date ON availabilities(prestataire_id, day_of_week, specific_date);

-- Financial: Requêtes courantes
CREATE INDEX idx_earning_prestataire_status ON financial_prestataire_earning(prestataire_id, status);
CREATE INDEX idx_earning_available ON financial_prestataire_earning(status, available_at);
CREATE INDEX idx_payout_status_requested ON financial_payout(status, requested_at);
CREATE INDEX idx_transaction_type_date ON financial_transaction(type, created_at);

-- Rapports admin
CREATE INDEX idx_booking_status_date ON bookings(status, scheduled_date);
CREATE INDEX idx_payment_status_created ON financial_payment(status, created_at);
```

---

## Contraintes d'Intégrité

### Contraintes Business
```sql
-- Un prestataire ne peut pas s'auto-remplacer
ALTER TABLE replacements 
ADD CONSTRAINT chk_no_self_replacement 
CHECK (original_prestataire_id != replacement_prestataire_id);

-- Un gain ne peut pas être négatif
ALTER TABLE financial_prestataire_earning 
ADD CONSTRAINT chk_positive_earning 
CHECK (net_amount >= 0);

-- Une commission ne peut pas dépasser le montant total
ALTER TABLE financial_commission 
ADD CONSTRAINT chk_commission_valid 
CHECK (commission_amount <= base_amount);

-- Le montant net = total - commission
ALTER TABLE financial_prestataire_earning 
ADD CONSTRAINT chk_earning_calculation 
CHECK (net_amount = total_amount - commission_amount);

-- Un versement ne peut pas dépasser le solde disponible
-- (Géré par la logique applicative)
```

---

## Triggers et Procédures Stockées

### Trigger: Mise à jour automatique de la moyenne des avis
```sql
DELIMITER //

CREATE TRIGGER update_prestataire_rating_after_review
AFTER INSERT ON reviews
FOR EACH ROW
BEGIN
    UPDATE users
    SET 
        average_rating = (
            SELECT AVG(rating) 
            FROM reviews 
            WHERE prestataire_id = NEW.prestataire_id
        ),
        total_reviews = (
            SELECT COUNT(*) 
            FROM reviews 
            WHERE prestataire_id = NEW.prestataire_id
        )
    WHERE id = NEW.prestataire_id;
END//

DELIMITER ;
```

### Trigger: Création automatique d'une transaction après paiement
```sql
DELIMITER //

CREATE TRIGGER create_transaction_after_payment
AFTER INSERT ON financial_payment
FOR EACH ROW
BEGIN
    INSERT INTO financial_transaction (
        type, user_id, booking_id, payment_id, 
        amount, currency, status, reference, created_at
    ) VALUES (
        'payment', NEW.client_id, NEW.booking_id, NEW.id,
        NEW.amount, NEW.currency, NEW.status,
        CONCAT('TXN-PAY-', NEW.id), NOW()
    );
END//

DELIMITER ;
```

---

## Volumétrie Estimée (après 1 an)

| Table | Estimation |
|-------|-----------|
| users | ~5 000 |
| service_requests | ~50 000 |
| quotes | ~150 000 |
| bookings | ~100 000 |
| financial_payment | ~100 000 |
| financial_commission | ~100 000 |
| financial_prestataire_earning | ~100 000 |
| financial_transaction | ~500 000 |
| financial_payout | ~10 000 |
| reviews | ~80 000 |
| notifications | ~1 000 000 |

---

## Stratégie de Sauvegarde

### Sauvegardes Quotidiennes
- Dump complet à 2h du matin
- Sauvegarde incrémentale toutes les 6h
- Rétention: 30 jours

### Sauvegardes Critiques (Financial)
- Sauvegarde des tables financial_* toutes les heures
- Logs binaires activés pour restauration point-in-time
- Réplication master-slave pour haute disponibilité

---

## Migration Doctrine

### Commandes de Migration
```bash
# Créer une migration
php bin/console make:migration

# Exécuter les migrations
php bin/console doctrine:migrations:migrate

# Créer le schéma depuis zéro
php bin/console doctrine:schema:create

# Mettre à jour le schéma
php bin/console doctrine:schema:update --force
```

---

## Sécurité des Données

### Chiffrement
- Mots de passe: bcrypt (cost 12)
- Données sensibles (IBAN, etc.): AES-256
- Communications: TLS 1.3

### RGPD
- Soft delete pour les utilisateurs
- Export des données personnelles
- Anonymisation après suppression
- Logs d'accès aux données sensibles

---

**Ce schéma de base de données est optimisé pour :**
✅ Performance (index stratégiques)  
✅ Intégrité (contraintes et triggers)  
✅ Traçabilité (module Transaction)  
✅ Évolutivité (structure modulaire)  
✅ Conformité RGPD  
✅ Module Financial autonome et intégré
