src/
├── 📁 Entity/
│   ├── 📁 Financial/
│   │   ├── Transaction.php              # Toutes les transactions
│   │   ├── PrestataireEarning.php      # Gains prestataire
│   │   ├── ClientExpense.php           # Dépenses client
│   │   ├── Payout.php                  # Virements vers prestataires
│   │   ├── Refund.php                  # Remboursements
│   │   ├── Commission.php              # Commissions plateforme
│   │   ├── BankAccount.php             # Comptes bancaires
│   │   └── FinancialReport.php         # Rapports financiers
│
├── 📁 Controller/Api/
│   ├── 📁 Client/
│   │   ├── FinancialController.php
│   │   ├── ExpenseController.php
│   │   ├── InvoiceController.php
│   │   └── RefundController.php
│   │
│   └── 📁 Prestataire/
│       ├── FinancialController.php
│       ├── EarningController.php
│       ├── PayoutController.php
│       ├── InvoiceController.php
│       └── TaxReportController.php
│
├── 📁 Service/
│   └── 📁 Financial/
│       ├── TransactionManager.php
│       ├── EarningCalculator.php
│       ├── CommissionCalculator.php
│       ├── PayoutService.php
│       ├── RefundService.php
│       ├── InvoiceGenerator.php
│       ├── TaxReportGenerator.php
│       └── FinancialStatisticsService.php
│
└── 📁 Repository/
    └── 📁 Financial/
        ├── TransactionRepository.php
        ├── PrestataireEarningRepository.php
        ├── ClientExpenseRepository.php
        ├── PayoutRepository.php
        └── CommissionRepository.php
		
		