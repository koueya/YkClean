# Architecture Globale du Projet - Plateforme de Services à Domicile

## Structure Globale Multi-Plateforme

```
project-root/
│
├── backend/                              # ← API Symfony
│   ├── config/
│   ├── migrations/
│   ├── public/
│   ├── src/
│   │   ├── Entity/
│   │   ├── Repository/
│   │   ├── Service/
│   │   ├── Controller/
│   │   ├── Module/
│   │   │   └── Financial/               # ← Module Financial autonome
│   │   └── ...
│   ├── tests/
│   ├── var/
│   ├── vendor/
│   └── composer.json
│
├── frontend-web/                         # ← Application Web React.js
│   ├── public/
│   ├── src/
│   │   ├── api/
│   │   ├── components/
│   │   │   ├── common/
│   │   │   ├── client/
│   │   │   ├── prestataire/
│   │   │   └── admin/
│   │   ├── pages/
│   │   │   ├── auth/
│   │   │   ├── client/
│   │   │   ├── prestataire/
│   │   │   ├── admin/
│   │   │   └── public/
│   │   ├── routes/
│   │   ├── store/
│   │   ├── hooks/
│   │   ├── services/
│   │   └── utils/
│   ├── package.json
│   └── vite.config.js
│
├── mobile/                               # ← Application Mobile React Native
│   ├── src/
│   │   ├── api/
│   │   ├── components/
│   │   ├── screens/
│   │   ├── navigation/
│   │   ├── store/
│   │   ├── services/
│   │   └── utils/
│   ├── App.js
│   └── package.json
│
├── docs/                                 # ← Documentation
│   ├── api/
│   ├── architecture/
│   └── deployment/
│
└── README.md
```

---

## Vue d'ensemble de l'Architecture

Cette architecture présente la structure complète du projet Symfony intégrant le **module Financial autonome** et tous les autres composants de la plateforme.

---

## Structure Complète du Projet

```
backend/
│
├── bin/
│   └── console
│
├── config/
│   ├── packages/
│   │   ├── doctrine.yaml
│   │   ├── framework.yaml
│   │   ├── security.yaml
│   │   ├── messenger.yaml
│   │   ├── financial.yaml              # ← Configuration du module Financial
│   │   ├── nelmio_cors.yaml
│   │   ├── api_platform.yaml
│   │   └── ...
│   │
│   ├── routes/
│   │   ├── api.yaml
│   │   ├── financial.yaml              # ← Routes du module Financial
│   │   └── ...
│   │
│   ├── services.yaml
│   └── routes.yaml
│
├── migrations/
│   └── Version*.php
│
├── public/
│   └── index.php
│
├── src/
│   │
│   ├── Entity/
│   │   ├── User/
│   │   │   ├── User.php (entité de base)
│   │   │   ├── Client.php
│   │   │   ├── Prestataire.php
│   │   │   └── Admin.php
│   │   │
│   │   ├── Service/
│   │   │   ├── ServiceRequest.php
│   │   │   ├── ServiceCategory.php
│   │   │   └── ServiceType.php
│   │   │
│   │   ├── Quote/
│   │   │   ├── Quote.php
│   │   │   └── QuoteStatus.php
│   │   │
│   │   ├── Booking/
│   │   │   ├── Booking.php
│   │   │   ├── BookingStatus.php
│   │   │   └── Recurrence.php
│   │   │
│   │   ├── Planning/
│   │   │   ├── Availability.php
│   │   │   ├── Schedule.php
│   │   │   ├── Absence.php
│   │   │   └── Replacement.php
│   │   │
│   │   ├── Rating/
│   │   │   └── Review.php
│   │   │
│   │   └── Notification/
│   │       └── Notification.php
│   │
│   ├── Repository/
│   │   ├── User/
│   │   │   ├── UserRepository.php
│   │   │   ├── ClientRepository.php
│   │   │   ├── PrestataireRepository.php
│   │   │   └── AdminRepository.php
│   │   │
│   │   ├── Service/
│   │   │   ├── ServiceRequestRepository.php
│   │   │   ├── ServiceCategoryRepository.php
│   │   │   └── ServiceTypeRepository.php
│   │   │
│   │   ├── Quote/
│   │   │   └── QuoteRepository.php
│   │   │
│   │   ├── Booking/
│   │   │   ├── BookingRepository.php
│   │   │   └── RecurrenceRepository.php
│   │   │
│   │   ├── Planning/
│   │   │   ├── AvailabilityRepository.php
│   │   │   ├── ScheduleRepository.php
│   │   │   ├── AbsenceRepository.php
│   │   │   └── ReplacementRepository.php
│   │   │
│   │   ├── Rating/
│   │   │   └── ReviewRepository.php
│   │   │
│   │   └── Notification/
│   │       └── NotificationRepository.php
│   │
│   ├── Service/
│   │   ├── Auth/
│   │   │   ├── AuthenticationService.php
│   │   │   ├── JWTService.php
│   │   │   └── PasswordResetService.php
│   │   │
│   │   ├── Booking/
│   │   │   ├── BookingService.php
│   │   │   └── RecurrenceService.php
│   │   │
│   │   ├── Quote/
│   │   │   └── QuoteService.php
│   │   │
│   │   ├── Planning/
│   │   │   ├── AvailabilityService.php
│   │   │   ├── AvailabilityManager.php
│   │   │   ├── ConflictDetector.php
│   │   │   └── ScheduleOptimizer.php
│   │   │
│   │   ├── Replacement/
│   │   │   └── ReplacementService.php
│   │   │
│   │   ├── Notification/
│   │   │   ├── NotificationService.php
│   │   │   ├── EmailNotifier.php
│   │   │   ├── SMSNotifier.php
│   │   │   └── PushNotifier.php
│   │   │
│   │   ├── Matching/
│   │   │   └── MatchingService.php
│   │   │
│   │   ├── Geolocation/
│   │   │   └── GeolocationService.php
│   │   │
│   │   └── Document/
│   │       └── DocumentValidationService.php
│   │
│   ├── Controller/
│   │   ├── Api/
│   │   │   ├── Auth/
│   │   │   │   ├── RegisterController.php
│   │   │   │   ├── LoginController.php
│   │   │   │   ├── RefreshTokenController.php
│   │   │   │   └── PasswordResetController.php
│   │   │   │
│   │   │   ├── Client/
│   │   │   │   ├── ProfileController.php
│   │   │   │   ├── ServiceRequestController.php
│   │   │   │   ├── QuoteController.php
│   │   │   │   ├── BookingController.php
│   │   │   │   └── ReviewController.php
│   │   │   │
│   │   │   ├── Prestataire/
│   │   │   │   ├── ProfileController.php
│   │   │   │   ├── AvailabilityController.php
│   │   │   │   ├── ServiceRequestController.php
│   │   │   │   ├── QuoteController.php
│   │   │   │   ├── BookingController.php
│   │   │   │   ├── AbsenceController.php
│   │   │   │   └── ReviewController.php
│   │   │   │
│   │   │   ├── Admin/
│   │   │   │   ├── UserManagementController.php
│   │   │   │   ├── PrestataireApprovalController.php
│   │   │   │   ├── ServiceCategoryController.php
│   │   │   │   ├── BookingManagementController.php
│   │   │   │   └── StatisticsController.php
│   │   │   │
│   │   │   └── Common/
│   │   │       ├── NotificationController.php
│   │   │       └── FileUploadController.php
│   │   │
│   │   └── WebhookController.php
│   │
│   ├── EventListener/
│   │   ├── BookingCompletedListener.php
│   │   ├── QuoteAcceptedListener.php
│   │   ├── ServiceRequestCreatedListener.php
│   │   ├── PrestataireApprovedListener.php
│   │   └── ReviewCreatedListener.php
│   │
│   ├── EventSubscriber/
│   │   ├── ExceptionSubscriber.php
│   │   ├── JWTCreatedSubscriber.php
│   │   └── RequestSubscriber.php
│   │
│   ├── Security/
│   │   ├── Voter/
│   │   │   ├── BookingVoter.php
│   │   │   ├── QuoteVoter.php
│   │   │   ├── ServiceRequestVoter.php
│   │   │   └── UserVoter.php
│   │   │
│   │   ├── JWTAuthenticator.php
│   │   └── LoginFormAuthenticator.php
│   │
│   ├── Validator/
│   │   ├── Constraints/
│   │   │   ├── ValidSiret.php
│   │   │   ├── ValidPhone.php
│   │   │   └── ValidPostalCode.php
│   │   │
│   │   └── Validator/
│   │       ├── ValidSiretValidator.php
│   │       ├── ValidPhoneValidator.php
│   │       └── ValidPostalCodeValidator.php
│   │
│   ├── DTO/
│   │   ├── Auth/
│   │   │   ├── RegisterDTO.php
│   │   │   └── LoginDTO.php
│   │   │
│   │   ├── Booking/
│   │   │   └── CreateBookingDTO.php
│   │   │
│   │   ├── Quote/
│   │   │   └── CreateQuoteDTO.php
│   │   │
│   │   └── ServiceRequest/
│   │       └── CreateServiceRequestDTO.php
│   │
│   ├── Enum/
│   │   ├── BookingStatus.php
│   │   ├── QuoteStatus.php
│   │   ├── ServiceRequestStatus.php
│   │   ├── UserRole.php
│   │   └── NotificationType.php
│   │
│   ├── Exception/
│   │   ├── BookingException.php
│   │   ├── QuoteException.php
│   │   ├── ServiceRequestException.php
│   │   ├── AuthenticationException.php
│   │   └── ValidationException.php
│   │
│   ├── MessageHandler/
│   │   ├── SendNotificationHandler.php
│   │   ├── ProcessBookingHandler.php
│   │   └── GenerateInvoiceHandler.php
│   │
│   ├── Command/
│   │   ├── ProcessScheduledBookingsCommand.php
│   │   ├── SendRemindersCommand.php
│   │   └── CleanupExpiredQuotesCommand.php
│   │
│   │
│   │ ═══════════════════════════════════════════════════════════════
│   │ ║                  MODULE FINANCIAL AUTONOME                  ║
│   │ ═══════════════════════════════════════════════════════════════
│   │
│   └── Module/
│       └── Financial/
│           │
│           ├── Entity/
│           │   ├── Payment.php
│           │   ├── Invoice.php
│           │   ├── Commission.php
│           │   ├── PrestataireEarning.php
│           │   ├── Transaction.php
│           │   ├── Payout.php
│           │   ├── CommissionRule.php
│           │   ├── PaymentMethod.php
│           │   ├── RefundRequest.php
│           │   └── FinancialReport.php
│           │
│           ├── Repository/
│           │   ├── PaymentRepository.php
│           │   ├── InvoiceRepository.php
│           │   ├── CommissionRepository.php
│           │   ├── PrestataireEarningRepository.php
│           │   ├── TransactionRepository.php
│           │   ├── PayoutRepository.php
│           │   ├── CommissionRuleRepository.php
│           │   ├── PaymentMethodRepository.php
│           │   ├── RefundRequestRepository.php
│           │   └── FinancialReportRepository.php
│           │
│           ├── Service/
│           │   ├── PaymentService.php
│           │   ├── InvoiceService.php
│           │   ├── CommissionService.php
│           │   ├── EarningService.php
│           │   ├── PayoutService.php
│           │   ├── TransactionService.php
│           │   ├── RefundService.php
│           │   ├── FinancialReportService.php
│           │   ├── BalanceService.php
│           │   ├── TaxCalculationService.php
│           │   └── Gateway/
│           │       ├── PaymentGatewayInterface.php
│           │       ├── StripeGateway.php
│           │       └── MangopayGateway.php
│           │
│           ├── Controller/
│           │   ├── Admin/
│           │   │   ├── CommissionController.php
│           │   │   ├── PayoutController.php
│           │   │   ├── TransactionController.php
│           │   │   ├── FinancialReportController.php
│           │   │   ├── CommissionRuleController.php
│           │   │   └── RefundController.php
│           │   │
│           │   ├── Prestataire/
│           │   │   ├── EarningController.php            # ← Déplacé depuis src/Controller/Api/Prestataire/
│           │   │   ├── PayoutRequestController.php
│           │   │   ├── InvoiceController.php
│           │   │   ├── TransactionHistoryController.php
│           │   │   └── BalanceController.php
│           │   │
│           │   ├── Client/
│           │   │   ├── PaymentController.php
│           │   │   ├── InvoiceController.php
│           │   │   ├── RefundRequestController.php
│           │   │   └── PaymentMethodController.php
│           │   │
│           │   └── WebhookController.php
│           │
│           ├── EventListener/
│           │   ├── BookingCompletedListener.php
│           │   ├── PaymentSuccessListener.php
│           │   ├── PaymentFailedListener.php
│           │   ├── EarningAvailableListener.php
│           │   ├── PayoutProcessedListener.php
│           │   └── CommissionCalculationListener.php
│           │
│           ├── EventSubscriber/
│           │   ├── TransactionLoggerSubscriber.php
│           │   └── FinancialNotificationSubscriber.php
│           │
│           ├── DTO/
│           │   ├── Request/
│           │   │   ├── CreatePaymentRequest.php
│           │   │   ├── RequestPayoutDTO.php
│           │   │   ├── CreateRefundRequest.php
│           │   │   └── UpdateCommissionRuleRequest.php
│           │   │
│           │   └── Response/
│           │       ├── EarningStatisticsDTO.php
│           │       ├── PayoutDetailsDTO.php
│           │       ├── FinancialReportDTO.php
│           │       ├── TransactionDTO.php
│           │       └── BalanceDTO.php
│           │
│           ├── Exception/
│           │   ├── InsufficientBalanceException.php
│           │   ├── PaymentGatewayException.php
│           │   ├── InvalidCommissionRuleException.php
│           │   ├── PayoutNotAllowedException.php
│           │   └── RefundException.php
│           │
│           ├── Validator/
│           │   ├── Constraints/
│           │   │   ├── ValidPaymentAmount.php
│           │   │   ├── ValidIBAN.php
│           │   │   └── SufficientBalance.php
│           │   │
│           │   └── Validator/
│           │       ├── ValidPaymentAmountValidator.php
│           │       ├── ValidIBANValidator.php
│           │       └── SufficientBalanceValidator.php
│           │
│           ├── Enum/
│           │   ├── PaymentStatus.php
│           │   ├── TransactionType.php
│           │   ├── PayoutStatus.php
│           │   ├── CommissionRuleType.php
│           │   ├── RefundStatus.php
│           │   └── InvoiceStatus.php
│           │
│           ├── Factory/
│           │   ├── PaymentFactory.php
│           │   ├── CommissionFactory.php
│           │   ├── EarningFactory.php
│           │   ├── TransactionFactory.php
│           │   └── InvoiceFactory.php
│           │
│           ├── Specification/
│           │   ├── CanRequestPayoutSpecification.php
│           │   ├── IsPaymentRefundableSpecification.php
│           │   └── CommissionRuleApplicableSpecification.php
│           │
│           ├── Strategy/
│           │   ├── CommissionCalculation/
│           │   │   ├── CommissionCalculationStrategyInterface.php
│           │   │   ├── PercentageCommissionStrategy.php
│           │   │   ├── FixedCommissionStrategy.php
│           │   │   └── TieredCommissionStrategy.php
│           │   │
│           │   └── Payout/
│           │       ├── PayoutStrategyInterface.php
│           │       ├── InstantPayoutStrategy.php
│           │       └── ScheduledPayoutStrategy.php
│           │
│           ├── Query/
│           │   ├── GetEarningsByPrestataireQuery.php
│           │   ├── GetTransactionHistoryQuery.php
│           │   ├── GetFinancialReportQuery.php
│           │   └── GetPendingPayoutsQuery.php
│           │
│           ├── Handler/
│           │   ├── GetEarningsByPrestataireHandler.php
│           │   ├── GetTransactionHistoryHandler.php
│           │   ├── GetFinancialReportHandler.php
│           │   └── GetPendingPayoutsHandler.php
│           │
│           ├── Event/
│           │   ├── PaymentCompletedEvent.php
│           │   ├── CommissionCalculatedEvent.php
│           │   ├── EarningCreatedEvent.php
│           │   ├── PayoutRequestedEvent.php
│           │   ├── PayoutProcessedEvent.php
│           │   └── RefundProcessedEvent.php
│           │
│           └── Tests/
│               ├── Unit/
│               │   ├── Service/
│               │   ├── Validator/
│               │   └── Strategy/
│               │
│               └── Integration/
│                   └── Controller/
│
├── tests/
│   ├── Unit/
│   ├── Integration/
│   └── Functional/
│
├── var/
│   ├── cache/
│   ├── log/
│   └── uploads/
│
├── vendor/
│
├── .env
├── .env.local
├── composer.json
├── composer.lock
├── symfony.lock
└── phpunit.xml.dist
```

---

## Architecture Frontend Web (React.js)

```
frontend-web/
│
├── public/
│   ├── index.html
│   ├── favicon.ico
│   ├── manifest.json
│   └── assets/
│       ├── images/
│       └── icons/
│
├── src/
│   │
│   ├── api/
│   │   ├── axios.config.js
│   │   ├── auth.api.js
│   │   ├── booking.api.js
│   │   ├── quote.api.js
│   │   ├── serviceRequest.api.js
│   │   ├── payment.api.js                # ← API Financial
│   │   ├── earning.api.js                # ← API Financial
│   │   ├── payout.api.js                 # ← API Financial
│   │   ├── commission.api.js             # ← API Financial
│   │   └── notification.api.js
│   │
│   ├── components/
│   │   │
│   │   ├── common/
│   │   │   ├── Layout/
│   │   │   │   ├── Header.jsx
│   │   │   │   ├── Footer.jsx
│   │   │   │   ├── Sidebar.jsx
│   │   │   │   └── MainLayout.jsx
│   │   │   │
│   │   │   ├── UI/
│   │   │   │   ├── Button.jsx
│   │   │   │   ├── Input.jsx
│   │   │   │   ├── Select.jsx
│   │   │   │   ├── Card.jsx
│   │   │   │   ├── Modal.jsx
│   │   │   │   ├── Table.jsx
│   │   │   │   ├── Pagination.jsx
│   │   │   │   ├── Loader.jsx
│   │   │   │   ├── Toast.jsx
│   │   │   │   ├── Badge.jsx
│   │   │   │   └── Avatar.jsx
│   │   │   │
│   │   │   ├── Form/
│   │   │   │   ├── FormField.jsx
│   │   │   │   ├── FormError.jsx
│   │   │   │   ├── DatePicker.jsx
│   │   │   │   ├── TimePicker.jsx
│   │   │   │   ├── FileUpload.jsx
│   │   │   │   └── AddressAutocomplete.jsx
│   │   │   │
│   │   │   └── Navigation/
│   │   │       ├── Breadcrumb.jsx
│   │   │       ├── Tabs.jsx
│   │   │       └── Stepper.jsx
│   │   │
│   │   ├── client/
│   │   │   ├── Dashboard/
│   │   │   │   ├── ClientDashboard.jsx
│   │   │   │   ├── QuickActions.jsx
│   │   │   │   └── RecentBookings.jsx
│   │   │   │
│   │   │   ├── ServiceRequest/
│   │   │   │   ├── ServiceRequestForm.jsx
│   │   │   │   ├── ServiceRequestList.jsx
│   │   │   │   ├── ServiceRequestCard.jsx
│   │   │   │   └── ServiceCategorySelector.jsx
│   │   │   │
│   │   │   ├── Quote/
│   │   │   │   ├── QuoteList.jsx
│   │   │   │   ├── QuoteCard.jsx
│   │   │   │   ├── QuoteComparison.jsx
│   │   │   │   └── QuoteDetails.jsx
│   │   │   │
│   │   │   ├── Booking/
│   │   │   │   ├── BookingList.jsx
│   │   │   │   ├── BookingCard.jsx
│   │   │   │   ├── BookingDetails.jsx
│   │   │   │   ├── BookingCalendar.jsx
│   │   │   │   └── CancelBookingModal.jsx
│   │   │   │
│   │   │   ├── Financial/                # ← Composants Financial
│   │   │   │   ├── PaymentForm.jsx
│   │   │   │   ├── PaymentMethodSelector.jsx
│   │   │   │   ├── InvoiceList.jsx
│   │   │   │   ├── InvoiceCard.jsx
│   │   │   │   ├── InvoiceDownload.jsx
│   │   │   │   ├── RefundRequestForm.jsx
│   │   │   │   └── PaymentHistory.jsx
│   │   │   │
│   │   │   └── Profile/
│   │   │       ├── ClientProfile.jsx
│   │   │       ├── EditProfile.jsx
│   │   │       └── ChangePassword.jsx
│   │   │
│   │   ├── prestataire/
│   │   │   ├── Dashboard/
│   │   │   │   ├── PrestataireDashboard.jsx
│   │   │   │   ├── Statistics.jsx
│   │   │   │   ├── UpcomingBookings.jsx
│   │   │   │   └── EarningsOverview.jsx   # ← Composant Financial
│   │   │   │
│   │   │   ├── ServiceRequest/
│   │   │   │   ├── AvailableRequestsList.jsx
│   │   │   │   ├── RequestCard.jsx
│   │   │   │   └── RequestDetails.jsx
│   │   │   │
│   │   │   ├── Quote/
│   │   │   │   ├── CreateQuoteForm.jsx
│   │   │   │   ├── QuoteList.jsx
│   │   │   │   ├── QuoteCard.jsx
│   │   │   │   └── QuoteTemplate.jsx
│   │   │   │
│   │   │   ├── Booking/
│   │   │   │   ├── BookingList.jsx
│   │   │   │   ├── BookingCard.jsx
│   │   │   │   ├── BookingDetails.jsx
│   │   │   │   ├── BookingActions.jsx
│   │   │   │   └── CompletionForm.jsx
│   │   │   │
│   │   │   ├── Planning/
│   │   │   │   ├── AvailabilityCalendar.jsx
│   │   │   │   ├── AvailabilityForm.jsx
│   │   │   │   ├── AvailabilityList.jsx
│   │   │   │   ├── AbsenceForm.jsx
│   │   │   │   ├── AbsenceList.jsx
│   │   │   │   ├── WeeklySchedule.jsx
│   │   │   │   └── MonthlyPlanner.jsx
│   │   │   │
│   │   │   ├── Financial/                # ← Composants Financial
│   │   │   │   ├── EarningsList.jsx
│   │   │   │   ├── EarningsCard.jsx
│   │   │   │   ├── EarningsStatistics.jsx
│   │   │   │   ├── EarningsChart.jsx
│   │   │   │   ├── BalanceWidget.jsx
│   │   │   │   ├── PayoutRequestForm.jsx
│   │   │   │   ├── PayoutList.jsx
│   │   │   │   ├── PayoutCard.jsx
│   │   │   │   ├── PayoutDetails.jsx
│   │   │   │   ├── TransactionHistory.jsx
│   │   │   │   ├── TransactionTable.jsx
│   │   │   │   ├── InvoiceGenerator.jsx
│   │   │   │   └── BankDetailsForm.jsx
│   │   │   │
│   │   │   └── Profile/
│   │   │       ├── PrestataireProfile.jsx
│   │   │       ├── EditProfile.jsx
│   │   │       ├── DocumentUpload.jsx
│   │   │       ├── ServiceCategories.jsx
│   │   │       └── ZoneSettings.jsx
│   │   │
│   │   └── admin/
│   │       ├── Dashboard/
│   │       │   ├── AdminDashboard.jsx
│   │       │   ├── StatisticsCards.jsx
│   │       │   ├── RevenueChart.jsx
│   │       │   └── ActivityFeed.jsx
│   │       │
│   │       ├── Users/
│   │       │   ├── UserList.jsx
│   │       │   ├── UserTable.jsx
│   │       │   ├── UserDetails.jsx
│   │       │   ├── UserActions.jsx
│   │       │   └── UserFilters.jsx
│   │       │
│   │       ├── Prestataires/
│   │       │   ├── PrestataireList.jsx
│   │       │   ├── PendingApprovals.jsx
│   │       │   ├── ApprovalCard.jsx
│   │       │   ├── DocumentViewer.jsx
│   │       │   └── ApprovalActions.jsx
│   │       │
│   │       ├── Bookings/
│   │       │   ├── BookingList.jsx
│   │       │   ├── BookingTable.jsx
│   │       │   ├── BookingFilters.jsx
│   │       │   └── BookingDetails.jsx
│   │       │
│   │       ├── ServiceCategories/
│   │       │   ├── CategoryList.jsx
│   │       │   ├── CategoryForm.jsx
│   │       │   └── CategoryCard.jsx
│   │       │
│   │       ├── Financial/                # ← Composants Financial Admin
│   │       │   ├── FinancialDashboard.jsx
│   │       │   ├── RevenueStatistics.jsx
│   │       │   ├── CommissionList.jsx
│   │       │   ├── CommissionTable.jsx
│   │       │   ├── CommissionDetails.jsx
│   │       │   ├── CommissionRules/
│   │       │   │   ├── RulesList.jsx
│   │       │   │   ├── RuleForm.jsx
│   │       │   │   └── RuleCard.jsx
│   │       │   ├── Payouts/
│   │       │   │   ├── PayoutList.jsx
│   │       │   │   ├── PayoutTable.jsx
│   │       │   │   ├── PayoutApproval.jsx
│   │       │   │   ├── PayoutDetails.jsx
│   │       │   │   └── BatchPayoutProcessor.jsx
│   │       │   ├── Transactions/
│   │       │   │   ├── TransactionList.jsx
│   │       │   │   ├── TransactionTable.jsx
│   │       │   │   ├── TransactionFilters.jsx
│   │       │   │   └── TransactionDetails.jsx
│   │       │   ├── Refunds/
│   │       │   │   ├── RefundList.jsx
│   │       │   │   ├── RefundApproval.jsx
│   │       │   │   └── RefundDetails.jsx
│   │       │   └── Reports/
│   │       │       ├── MonthlyReport.jsx
│   │       │       ├── AnnualReport.jsx
│   │       │       ├── RevenueReport.jsx
│   │       │       ├── TopEarnersReport.jsx
│   │       │       └── ExportReports.jsx
│   │       │
│   │       └── Settings/
│   │           ├── PlatformSettings.jsx
│   │           ├── NotificationSettings.jsx
│   │           └── SystemConfiguration.jsx
│   │
│   ├── pages/
│   │   │
│   │   ├── auth/
│   │   │   ├── LoginPage.jsx
│   │   │   ├── RegisterPage.jsx
│   │   │   ├── ForgotPasswordPage.jsx
│   │   │   └── ResetPasswordPage.jsx
│   │   │
│   │   ├── client/
│   │   │   ├── ClientHomePage.jsx
│   │   │   ├── CreateServiceRequestPage.jsx
│   │   │   ├── ServiceRequestsPage.jsx
│   │   │   ├── QuotesPage.jsx
│   │   │   ├── BookingsPage.jsx
│   │   │   ├── BookingDetailsPage.jsx
│   │   │   ├── PaymentPage.jsx           # ← Page Financial
│   │   │   ├── InvoicesPage.jsx          # ← Page Financial
│   │   │   ├── PaymentHistoryPage.jsx    # ← Page Financial
│   │   │   └── ProfilePage.jsx
│   │   │
│   │   ├── prestataire/
│   │   │   ├── PrestataireHomePage.jsx
│   │   │   ├── AvailableRequestsPage.jsx
│   │   │   ├── MyQuotesPage.jsx
│   │   │   ├── CreateQuotePage.jsx
│   │   │   ├── MyBookingsPage.jsx
│   │   │   ├── BookingDetailsPage.jsx
│   │   │   ├── PlanningPage.jsx
│   │   │   ├── AvailabilityPage.jsx
│   │   │   ├── EarningsPage.jsx          # ← Page Financial
│   │   │   ├── PayoutsPage.jsx           # ← Page Financial
│   │   │   ├── TransactionsPage.jsx      # ← Page Financial
│   │   │   ├── InvoicesPage.jsx          # ← Page Financial
│   │   │   └── ProfilePage.jsx
│   │   │
│   │   ├── admin/
│   │   │   ├── AdminHomePage.jsx
│   │   │   ├── UsersManagementPage.jsx
│   │   │   ├── PrestataireApprovalsPage.jsx
│   │   │   ├── BookingsManagementPage.jsx
│   │   │   ├── CategoriesManagementPage.jsx
│   │   │   ├── FinancialDashboardPage.jsx    # ← Page Financial
│   │   │   ├── CommissionsPage.jsx           # ← Page Financial
│   │   │   ├── CommissionRulesPage.jsx       # ← Page Financial
│   │   │   ├── PayoutsManagementPage.jsx     # ← Page Financial
│   │   │   ├── TransactionsPage.jsx          # ← Page Financial
│   │   │   ├── RefundsPage.jsx               # ← Page Financial
│   │   │   ├── FinancialReportsPage.jsx      # ← Page Financial
│   │   │   ├── StatisticsPage.jsx
│   │   │   └── SettingsPage.jsx
│   │   │
│   │   ├── public/
│   │   │   ├── LandingPage.jsx
│   │   │   ├── AboutPage.jsx
│   │   │   ├── ServicesPage.jsx
│   │   │   ├── HowItWorksPage.jsx
│   │   │   ├── PricingPage.jsx
│   │   │   ├── ContactPage.jsx
│   │   │   └── FAQPage.jsx
│   │   │
│   │   └── error/
│   │       ├── NotFoundPage.jsx
│   │       ├── UnauthorizedPage.jsx
│   │       └── ServerErrorPage.jsx
│   │
│   ├── routes/
│   │   ├── AppRoutes.jsx
│   │   ├── AuthRoutes.jsx
│   │   ├── ClientRoutes.jsx
│   │   ├── PrestataireRoutes.jsx
│   │   ├── AdminRoutes.jsx
│   │   ├── PublicRoutes.jsx
│   │   └── ProtectedRoute.jsx
│   │
│   ├── store/
│   │   ├── slices/
│   │   │   ├── authSlice.js
│   │   │   ├── userSlice.js
│   │   │   ├── bookingSlice.js
│   │   │   ├── quoteSlice.js
│   │   │   ├── serviceRequestSlice.js
│   │   │   ├── financialSlice.js         # ← Store Financial
│   │   │   ├── earningSlice.js           # ← Store Financial
│   │   │   ├── payoutSlice.js            # ← Store Financial
│   │   │   ├── notificationSlice.js
│   │   │   └── uiSlice.js
│   │   │
│   │   ├── middlewares/
│   │   │   ├── authMiddleware.js
│   │   │   └── errorMiddleware.js
│   │   │
│   │   └── store.js
│   │
│   ├── hooks/
│   │   ├── useAuth.js
│   │   ├── useBooking.js
│   │   ├── useQuote.js
│   │   ├── useServiceRequest.js
│   │   ├── usePayment.js                 # ← Hook Financial
│   │   ├── useEarnings.js                # ← Hook Financial
│   │   ├── usePayout.js                  # ← Hook Financial
│   │   ├── useNotification.js
│   │   ├── useDebounce.js
│   │   ├── useLocalStorage.js
│   │   └── useWindowSize.js
│   │
│   ├── services/
│   │   ├── AuthService.js
│   │   ├── BookingService.js
│   │   ├── QuoteService.js
│   │   ├── ServiceRequestService.js
│   │   ├── PaymentService.js             # ← Service Financial
│   │   ├── EarningService.js             # ← Service Financial
│   │   ├── PayoutService.js              # ← Service Financial
│   │   ├── NotificationService.js
│   │   ├── GeolocationService.js
│   │   ├── FileUploadService.js
│   │   └── StorageService.js
│   │
│   ├── utils/
│   │   ├── constants.js
│   │   ├── validators.js
│   │   ├── formatters.js
│   │   ├── dateHelpers.js
│   │   ├── currencyHelpers.js
│   │   ├── apiHelpers.js
│   │   └── errorHandlers.js
│   │
│   ├── contexts/
│   │   ├── AuthContext.jsx
│   │   ├── ThemeContext.jsx
│   │   └── NotificationContext.jsx
│   │
│   ├── styles/
│   │   ├── theme/
│   │   │   ├── colors.js
│   │   │   ├── typography.js
│   │   │   ├── spacing.js
│   │   │   └── breakpoints.js
│   │   │
│   │   ├── global.css
│   │   └── variables.css
│   │
│   ├── assets/
│   │   ├── images/
│   │   ├── icons/
│   │   └── fonts/
│   │
│   ├── config/
│   │   ├── api.config.js
│   │   ├── stripe.config.js              # ← Config Financial
│   │   └── env.config.js
│   │
│   ├── App.jsx
│   ├── main.jsx
│   └── index.css
│
├── .env
├── .env.example
├── .gitignore
├── package.json
├── vite.config.js (ou webpack.config.js)
├── tailwind.config.js
└── README.md
```

---

## Architecture Mobile (React Native)

```
mobile/
│
├── src/
│   │
│   ├── api/
│   │   ├── client.js
│   │   ├── auth.js
│   │   ├── booking.js
│   │   ├── quote.js
│   │   ├── payment.js                    # ← Appels API Financial
│   │   ├── earning.js                    # ← Appels API Financial
│   │   └── notification.js
│   │
│   ├── components/
│   │   ├── common/
│   │   │   ├── Button.js
│   │   │   ├── Card.js
│   │   │   ├── Input.js
│   │   │   ├── Modal.js
│   │   │   └── Loader.js
│   │   │
│   │   ├── client/
│   │   │   ├── ServiceRequestForm.js
│   │   │   ├── QuoteCard.js
│   │   │   ├── BookingCard.js
│   │   │   ├── PaymentForm.js            # ← Composant Financial
│   │   │   └── InvoiceCard.js            # ← Composant Financial
│   │   │
│   │   └── prestataire/
│   │       ├── QuoteForm.js
│   │       ├── AvailabilityCalendar.js
│   │       ├── BookingCard.js
│   │       ├── EarningCard.js            # ← Composant Financial
│   │       ├── PayoutRequestForm.js      # ← Composant Financial
│   │       └── BalanceWidget.js          # ← Composant Financial
│   │
│   ├── screens/
│   │   ├── auth/
│   │   │   ├── LoginScreen.js
│   │   │   ├── RegisterScreen.js
│   │   │   └── ForgotPasswordScreen.js
│   │   │
│   │   ├── client/
│   │   │   ├── HomeScreen.js
│   │   │   ├── CreateRequestScreen.js
│   │   │   ├── QuotesListScreen.js
│   │   │   ├── BookingsListScreen.js
│   │   │   ├── BookingDetailsScreen.js
│   │   │   ├── PaymentScreen.js          # ← Écran Financial
│   │   │   ├── InvoicesScreen.js         # ← Écran Financial
│   │   │   └── ProfileScreen.js
│   │   │
│   │   └── prestataire/
│   │       ├── HomeScreen.js
│   │       ├── RequestsListScreen.js
│   │       ├── CreateQuoteScreen.js
│   │       ├── PlanningScreen.js
│   │       ├── BookingsListScreen.js
│   │       ├── BookingDetailsScreen.js
│   │       ├── AvailabilitySettingsScreen.js
│   │       ├── EarningsScreen.js         # ← Écran Financial
│   │       ├── PayoutScreen.js           # ← Écran Financial
│   │       ├── TransactionsScreen.js     # ← Écran Financial
│   │       └── ProfileScreen.js
│   │
│   ├── navigation/
│   │   ├── AppNavigator.js
│   │   ├── ClientNavigator.js
│   │   ├── PrestataireNavigator.js
│   │   └── AuthNavigator.js
│   │
│   ├── store/
│   │   ├── slices/
│   │   │   ├── authSlice.js
│   │   │   ├── bookingSlice.js
│   │   │   ├── quoteSlice.js
│   │   │   ├── financialSlice.js         # ← Store Financial
│   │   │   └── notificationSlice.js
│   │   │
│   │   └── store.js
│   │
│   ├── services/
│   │   ├── AuthService.js
│   │   ├── BookingService.js
│   │   ├── GeolocationService.js
│   │   ├── NotificationService.js
│   │   ├── PaymentService.js             # ← Service Financial
│   │   └── StorageService.js
│   │
│   ├── utils/
│   │   ├── constants.js
│   │   ├── validators.js
│   │   ├── formatters.js
│   │   └── helpers.js
│   │
│   ├── hooks/
│   │   ├── useAuth.js
│   │   ├── useBooking.js
│   │   ├── usePayment.js                 # ← Hook Financial
│   │   └── useNotification.js
│   │
│   └── theme/
│       ├── colors.js
│       ├── typography.js
│       └── spacing.js
│
├── App.js
├── package.json
└── app.json
```

---

## Flux de Données Global

### 1. Flux de Réservation et Paiement

```
┌─────────────────────────────────────────────────────────────────┐
│                      FLUX DE RÉSERVATION                        │
└─────────────────────────────────────────────────────────────────┘

1. Client crée une demande de service
   ↓
   [ServiceRequestController] → [ServiceRequest créé]
   ↓
2. Système notifie les prestataires dans la zone
   ↓
   [MatchingService] → [NotificationService]
   ↓
3. Prestataires consultent et envoient des devis
   ↓
   [QuoteController] → [Quote créé]
   ↓
4. Client sélectionne un devis
   ↓
   [QuoteController::accept] → [Booking créé]
   ↓
5. Redirection vers le paiement
   ↓
   ┌─────────────────────────────────────────────┐
   │      MODULE FINANCIAL PREND LE RELAIS       │
   └─────────────────────────────────────────────┘
   ↓
6. [PaymentService] → Traite le paiement (Stripe/Mangopay)
   ↓
7. [Event: PaymentCompletedEvent]
   ↓
8. [TransactionService] → Enregistre Transaction (PAYMENT)
   ↓
9. [CommissionService] → Calcule commission
   ↓
10. [Event: CommissionCalculatedEvent]
    ↓
11. [TransactionService] → Enregistre Transaction (COMMISSION)
    ↓
12. [EarningService] → Calcule gain net prestataire
    ↓
13. [Event: EarningCreatedEvent]
    ↓
14. [TransactionService] → Enregistre Transaction (EARNING)
    ↓
15. [InvoiceService] → Génère facture client
    ↓
16. [NotificationService] → Confirme paiement au client et prestataire
```

### 2. Flux de Versement aux Prestataires

```
┌─────────────────────────────────────────────────────────────────┐
│                   FLUX DE VERSEMENT (PAYOUT)                    │
└─────────────────────────────────────────────────────────────────┘

1. Service complété → Earning marqué "AVAILABLE" après 7 jours
   ↓
2. Prestataire consulte son solde disponible
   ↓
   [BalanceService::getAvailableBalance]
   ↓
3. Prestataire demande un versement
   ↓
   [PayoutRequestController] → [PayoutService::requestPayout]
   ↓
4. Vérifications :
   - Montant minimum atteint (50€)
   - Earnings disponibles
   - Informations bancaires valides
   ↓
5. [Payout créé avec statut PENDING]
   ↓
6. [Event: PayoutRequestedEvent]
   ↓
7. Admin approuve le versement
   ↓
   [PayoutController::approve]
   ↓
8. [PayoutService::processPayout]
   ↓
9. Appel gateway (Stripe/Mangopay)
   ↓
10. [Payout statut → COMPLETED]
    ↓
11. [Event: PayoutProcessedEvent]
    ↓
12. [TransactionService] → Enregistre Transaction (PAYOUT)
    ↓
13. [Earnings marqués comme PAID]
    ↓
14. [NotificationService] → Notifie le prestataire
```

---

## Intégration Module Financial avec les autres composants

### Relations entre entités

```
┌──────────────────────────────────────────────────────────────┐
│                    RELATIONS ENTRE MODULES                    │
└──────────────────────────────────────────────────────────────┘

src/Entity/Booking/Booking
    ↓ (OneToOne)
Module/Financial/Entity/Payment
    ↓ (OneToOne)
Module/Financial/Entity/Commission
    ↓ (OneToOne)
Module/Financial/Entity/PrestataireEarning
    ↓ (ManyToOne)
Module/Financial/Entity/Payout

────────────────────────────────────────────────────────────────

src/Entity/User/Prestataire
    ↓ (OneToMany)
Module/Financial/Entity/PrestataireEarning
    ↓ (OneToMany)
Module/Financial/Entity/Payout
    ↓ (OneToMany)
Module/Financial/Entity/Invoice

────────────────────────────────────────────────────────────────

src/Entity/User/Client
    ↓ (OneToMany)
Module/Financial/Entity/Payment
    ↓ (OneToMany)
Module/Financial/Entity/Invoice
    ↓ (OneToMany)
Module/Financial/Entity/RefundRequest
```

### Communication entre services

```
BookingService (src/Service/Booking/)
    ↓ utilise
PaymentService (Module/Financial/Service/)
    ↓ déclenche
CommissionService (Module/Financial/Service/)
    ↓ déclenche
EarningService (Module/Financial/Service/)

────────────────────────────────────────────────────────────────

NotificationService (src/Service/Notification/)
    ↓ écoute
Financial Events (Module/Financial/Event/)
    - PaymentCompletedEvent
    - PayoutProcessedEvent
    - RefundProcessedEvent
```

---

## Configuration Complète

### 1. Services principaux (`config/services.yaml`)

```yaml
services:
    _defaults:
        autowire: true
        autoconfigure: true

    # ═══════════════════════════════════════════════
    # SERVICES PRINCIPAUX DE L'APPLICATION
    # ═══════════════════════════════════════════════
    
    # Auto-configuration des services généraux
    App\:
        resource: '../src/'
        exclude:
            - '../src/Entity/'
            - '../src/Module/'
            - '../src/Kernel.php'

    # Controllers
    App\Controller\:
        resource: '../src/Controller/'
        tags: ['controller.service_arguments']

    # Event Listeners & Subscribers
    App\EventListener\:
        resource: '../src/EventListener/'
    
    App\EventSubscriber\:
        resource: '../src/EventSubscriber/'

    # ═══════════════════════════════════════════════
    # MODULE FINANCIAL (Autonome)
    # ═══════════════════════════════════════════════
    
    # Voir config/packages/financial.yaml pour la configuration complète
    
    # Auto-configuration du module Financial
    App\Module\Financial\:
        resource: '../src/Module/Financial/'
        exclude:
            - '../src/Module/Financial/Entity/'
            - '../src/Module/Financial/DTO/'
            - '../src/Module/Financial/Enum/'
            - '../src/Module/Financial/Exception/'
            - '../src/Module/Financial/Event/'
            - '../src/Module/Financial/Query/'
            - '../src/Module/Financial/Tests/'

    # Controllers Financial
    App\Module\Financial\Controller\:
        resource: '../src/Module/Financial/Controller/'
        tags: ['controller.service_arguments']
```

### 2. Routes principales (`config/routes.yaml`)

```yaml
# Routes générales
api:
    resource: '../src/Controller/Api/'
    type: attribute
    prefix: /api

# Module Financial (routes isolées)
financial:
    resource: '../config/routes/financial.yaml'
    prefix: /api
```

### 3. Doctrine mappings (`config/packages/doctrine.yaml`)

```yaml
doctrine:
    orm:
        auto_mapping: true
        mappings:
            # Entités principales
            App:
                is_bundle: false
                type: attribute
                dir: '%kernel.project_dir%/src/Entity'
                prefix: 'App\Entity'
                alias: App
            
            # Module Financial
            Financial:
                is_bundle: false
                type: attribute
                dir: '%kernel.project_dir%/src/Module/Financial/Entity'
                prefix: 'App\Module\Financial\Entity'
                alias: Financial
```

---

## API Endpoints - Vue Complète

### Authentification
```
POST   /api/register
POST   /api/login
POST   /api/refresh-token
POST   /api/forgot-password
POST   /api/reset-password
```

### Client
```
# Profil
GET    /api/client/profile
PUT    /api/client/profile

# Demandes de service
POST   /api/client/service-requests
GET    /api/client/service-requests
GET    /api/client/service-requests/{id}

# Devis
GET    /api/client/service-requests/{id}/quotes
POST   /api/client/quotes/{id}/accept

# Réservations
GET    /api/client/bookings
GET    /api/client/bookings/{id}
POST   /api/client/bookings/{id}/cancel

# Avis
POST   /api/client/reviews

# === FINANCIAL (Client) ===
POST   /api/client/financial/payments
GET    /api/client/financial/payments
GET    /api/client/financial/invoices
POST   /api/client/financial/refunds/request
```

### Prestataire
```
# Profil
GET    /api/prestataire/profile
PUT    /api/prestataire/profile

# Disponibilités
POST   /api/prestataire/availabilities
GET    /api/prestataire/availabilities
PUT    /api/prestataire/availabilities/{id}
DELETE /api/prestataire/availabilities/{id}

# Absences
POST   /api/prestataire/absences
GET    /api/prestataire/absences

# Demandes de service (dans la zone)
GET    /api/prestataire/service-requests

# Devis
POST   /api/prestataire/quotes
GET    /api/prestataire/quotes

# Réservations
GET    /api/prestataire/bookings
PUT    /api/prestataire/bookings/{id}/status

# Avis
GET    /api/prestataire/reviews

# === FINANCIAL (Prestataire) ===
GET    /api/prestataire/financial/earnings
GET    /api/prestataire/financial/earnings/statistics
POST   /api/prestataire/financial/payouts/request
GET    /api/prestataire/financial/payouts
GET    /api/prestataire/financial/balance
GET    /api/prestataire/financial/invoices
GET    /api/prestataire/financial/transactions
```

### Admin
```
# Gestion utilisateurs
GET    /api/admin/users
GET    /api/admin/users/{id}
PUT    /api/admin/users/{id}
DELETE /api/admin/users/{id}

# Approbation prestataires
GET    /api/admin/prestataires/pending
POST   /api/admin/prestataires/{id}/approve
POST   /api/admin/prestataires/{id}/reject

# Catégories de service
GET    /api/admin/service-categories
POST   /api/admin/service-categories

# Gestion réservations
GET    /api/admin/bookings
GET    /api/admin/bookings/{id}

# Statistiques
GET    /api/admin/statistics

# === FINANCIAL (Admin) ===
GET    /api/admin/financial/dashboard
GET    /api/admin/financial/commissions
GET    /api/admin/financial/commission-rules
GET    /api/admin/financial/earnings
GET    /api/admin/financial/payouts
POST   /api/admin/financial/payouts/{id}/approve
POST   /api/admin/financial/payouts/{id}/process
GET    /api/admin/financial/transactions
GET    /api/admin/financial/reports/monthly
GET    /api/admin/financial/refunds
```

---

## Technologies et Dépendances

### Backend (Symfony)

```json
{
    "require": {
        "php": "^8.2",
        "symfony/framework-bundle": "^6.4",
        "symfony/orm-pack": "^2.4",
        "symfony/maker-bundle": "^1.52",
        "symfony/security-bundle": "^6.4",
        "symfony/validator": "^6.4",
        "symfony/serializer": "^6.4",
        "symfony/mailer": "^6.4",
        "symfony/notifier": "^6.4",
        "symfony/messenger": "^6.4",
        
        "doctrine/orm": "^2.16",
        "doctrine/doctrine-bundle": "^2.11",
        "doctrine/doctrine-migrations-bundle": "^3.3",
        
        "lexik/jwt-authentication-bundle": "^2.20",
        "gesdinet/jwt-refresh-token-bundle": "^1.2",
        
        "nelmio/cors-bundle": "^2.4",
        "api-platform/core": "^3.2",
        
        "stripe/stripe-php": "^13.0",
        "twilio/sdk": "^7.0",
        "firebase/php-jwt": "^6.9"
    },
    "require-dev": {
        "symfony/phpunit-bridge": "^6.4",
        "phpunit/phpunit": "^10.5"
    }
}
```

### Frontend Web (React.js)

```json
{
    "dependencies": {
        "react": "^18.2.0",
        "react-dom": "^18.2.0",
        "react-router-dom": "^6.20.1",
        "@reduxjs/toolkit": "^2.0.1",
        "react-redux": "^9.0.4",
        "axios": "^1.6.2",
        
        "@stripe/stripe-js": "^2.4.0",
        "@stripe/react-stripe-js": "^2.4.0",
        
        "react-hook-form": "^7.49.2",
        "yup": "^1.3.3",
        "@hookform/resolvers": "^3.3.3",
        
        "recharts": "^2.10.3",
        "date-fns": "^3.0.6",
        "react-datepicker": "^4.25.0",
        "react-select": "^5.8.0",
        "react-toastify": "^9.1.3",
        
        "tailwindcss": "^3.4.0",
        "@headlessui/react": "^1.7.17",
        "@heroicons/react": "^2.1.1",
        
        "framer-motion": "^10.16.16",
        "react-spinners": "^0.13.8"
    },
    "devDependencies": {
        "@types/react": "^18.2.45",
        "@types/react-dom": "^18.2.18",
        "@vitejs/plugin-react": "^4.2.1",
        "vite": "^5.0.8",
        "autoprefixer": "^10.4.16",
        "postcss": "^8.4.32",
        "eslint": "^8.56.0",
        "prettier": "^3.1.1"
    }
}
```

### Frontend Mobile (React Native)

```json
{
    "dependencies": {
        "react": "18.2.0",
        "react-native": "0.73.0",
        "@react-navigation/native": "^6.1.9",
        "@react-navigation/stack": "^6.3.20",
        "@reduxjs/toolkit": "^2.0.1",
        "react-redux": "^9.0.4",
        "axios": "^1.6.2",
        "@stripe/stripe-react-native": "^0.35.0",
        "react-native-push-notification": "^8.1.1",
        "react-native-maps": "^1.10.0",
        "react-native-calendar-picker": "^7.1.3",
        "formik": "^2.4.5",
        "yup": "^1.3.3"
    }
}
```

---

## Routes Frontend Web (React Router)

### Routes Publiques
```javascript
// src/routes/PublicRoutes.jsx
export const publicRoutes = [
    { path: '/', element: <LandingPage /> },
    { path: '/about', element: <AboutPage /> },
    { path: '/services', element: <ServicesPage /> },
    { path: '/how-it-works', element: <HowItWorksPage /> },
    { path: '/pricing', element: <PricingPage /> },
    { path: '/contact', element: <ContactPage /> },
    { path: '/faq', element: <FAQPage /> },
];
```

### Routes Authentification
```javascript
// src/routes/AuthRoutes.jsx
export const authRoutes = [
    { path: '/login', element: <LoginPage /> },
    { path: '/register', element: <RegisterPage /> },
    { path: '/forgot-password', element: <ForgotPasswordPage /> },
    { path: '/reset-password/:token', element: <ResetPasswordPage /> },
];
```

### Routes Client (Protégées)
```javascript
// src/routes/ClientRoutes.jsx
export const clientRoutes = [
    { path: '/client', element: <ClientHomePage /> },
    { path: '/client/service-request/create', element: <CreateServiceRequestPage /> },
    { path: '/client/service-requests', element: <ServiceRequestsPage /> },
    { path: '/client/quotes', element: <QuotesPage /> },
    { path: '/client/bookings', element: <BookingsPage /> },
    { path: '/client/bookings/:id', element: <BookingDetailsPage /> },
    
    // Routes Financial
    { path: '/client/payment/:bookingId', element: <PaymentPage /> },
    { path: '/client/invoices', element: <InvoicesPage /> },
    { path: '/client/payment-history', element: <PaymentHistoryPage /> },
    
    { path: '/client/profile', element: <ProfilePage /> },
];
```

### Routes Prestataire (Protégées)
```javascript
// src/routes/PrestataireRoutes.jsx
export const prestataireRoutes = [
    { path: '/prestataire', element: <PrestataireHomePage /> },
    { path: '/prestataire/requests', element: <AvailableRequestsPage /> },
    { path: '/prestataire/quotes', element: <MyQuotesPage /> },
    { path: '/prestataire/quotes/create/:requestId', element: <CreateQuotePage /> },
    { path: '/prestataire/bookings', element: <MyBookingsPage /> },
    { path: '/prestataire/bookings/:id', element: <BookingDetailsPage /> },
    { path: '/prestataire/planning', element: <PlanningPage /> },
    { path: '/prestataire/availability', element: <AvailabilityPage /> },
    
    // Routes Financial
    { path: '/prestataire/earnings', element: <EarningsPage /> },
    { path: '/prestataire/payouts', element: <PayoutsPage /> },
    { path: '/prestataire/transactions', element: <TransactionsPage /> },
    { path: '/prestataire/invoices', element: <InvoicesPage /> },
    
    { path: '/prestataire/profile', element: <ProfilePage /> },
];
```

### Routes Admin (Protégées)
```javascript
// src/routes/AdminRoutes.jsx
export const adminRoutes = [
    { path: '/admin', element: <AdminHomePage /> },
    { path: '/admin/users', element: <UsersManagementPage /> },
    { path: '/admin/prestataires/approvals', element: <PrestataireApprovalsPage /> },
    { path: '/admin/bookings', element: <BookingsManagementPage /> },
    { path: '/admin/categories', element: <CategoriesManagementPage /> },
    
    // Routes Financial
    { path: '/admin/financial', element: <FinancialDashboardPage /> },
    { path: '/admin/financial/commissions', element: <CommissionsPage /> },
    { path: '/admin/financial/commission-rules', element: <CommissionRulesPage /> },
    { path: '/admin/financial/payouts', element: <PayoutsManagementPage /> },
    { path: '/admin/financial/transactions', element: <TransactionsPage /> },
    { path: '/admin/financial/refunds', element: <RefundsPage /> },
    { path: '/admin/financial/reports', element: <FinancialReportsPage /> },
    
    { path: '/admin/statistics', element: <StatisticsPage /> },
    { path: '/admin/settings', element: <SettingsPage /> },
];
```

---

## Exemples de Composants Clés Frontend Web

### 1. Layout Principal
```jsx
// src/components/common/Layout/MainLayout.jsx
import React from 'react';
import Header from './Header';
import Sidebar from './Sidebar';
import Footer from './Footer';

const MainLayout = ({ children, role }) => {
    return (
        <div className="min-h-screen flex flex-col">
            <Header role={role} />
            <div className="flex flex-1">
                <Sidebar role={role} />
                <main className="flex-1 p-6 bg-gray-50">
                    {children}
                </main>
            </div>
            <Footer />
        </div>
    );
};

export default MainLayout;
```

### 2. Composant de Paiement (Financial)
```jsx
// src/components/client/Financial/PaymentForm.jsx
import React, { useState } from 'react';
import { useStripe, useElements, CardElement } from '@stripe/react-stripe-js';
import { usePayment } from '../../../hooks/usePayment';
import Button from '../../common/UI/Button';
import Card from '../../common/UI/Card';

const PaymentForm = ({ bookingId, amount }) => {
    const stripe = useStripe();
    const elements = useElements();
    const { processPayment, loading } = usePayment();
    const [error, setError] = useState(null);

    const handleSubmit = async (e) => {
        e.preventDefault();
        
        if (!stripe || !elements) return;

        const { error, paymentMethod } = await stripe.createPaymentMethod({
            type: 'card',
            card: elements.getElement(CardElement),
        });

        if (error) {
            setError(error.message);
        } else {
            await processPayment(bookingId, paymentMethod.id);
        }
    };

    return (
        <Card>
            <h2 className="text-2xl font-bold mb-4">Paiement</h2>
            <p className="mb-4">Montant à payer : {amount} €</p>
            
            <form onSubmit={handleSubmit}>
                <div className="mb-4">
                    <CardElement
                        options={{
                            style: {
                                base: {
                                    fontSize: '16px',
                                    color: '#424770',
                                    '::placeholder': {
                                        color: '#aab7c4',
                                    },
                                },
                            },
                        }}
                    />
                </div>
                
                {error && (
                    <div className="text-red-600 mb-4">{error}</div>
                )}
                
                <Button
                    type="submit"
                    disabled={!stripe || loading}
                    loading={loading}
                    fullWidth
                >
                    Payer {amount} €
                </Button>
            </form>
        </Card>
    );
};

export default PaymentForm;
```

### 3. Tableau des Gains (Prestataire - Financial)
```jsx
// src/components/prestataire/Financial/EarningsList.jsx
import React, { useEffect } from 'react';
import { useEarnings } from '../../../hooks/useEarnings';
import Table from '../../common/UI/Table';
import Badge from '../../common/UI/Badge';
import { formatCurrency, formatDate } from '../../../utils/formatters';

const EarningsList = () => {
    const { earnings, loading, fetchEarnings } = useEarnings();

    useEffect(() => {
        fetchEarnings();
    }, []);

    const columns = [
        { key: 'earnedAt', label: 'Date', render: (date) => formatDate(date) },
        { key: 'booking.service', label: 'Service' },
        { key: 'totalAmount', label: 'Montant total', render: formatCurrency },
        { key: 'commissionAmount', label: 'Commission', render: formatCurrency },
        { key: 'netAmount', label: 'Net', render: formatCurrency },
        {
            key: 'status',
            label: 'Statut',
            render: (status) => (
                <Badge
                    color={
                        status === 'paid' ? 'green' :
                        status === 'available' ? 'blue' :
                        'yellow'
                    }
                >
                    {status}
                </Badge>
            ),
        },
    ];

    return (
        <div>
            <h2 className="text-2xl font-bold mb-4">Mes Gains</h2>
            <Table
                columns={columns}
                data={earnings}
                loading={loading}
                emptyMessage="Aucun gain pour le moment"
            />
        </div>
    );
};

export default EarningsList;
```

### 4. Dashboard Admin Financial
```jsx
// src/pages/admin/FinancialDashboardPage.jsx
import React from 'react';
import StatisticsCards from '../../components/admin/Dashboard/StatisticsCards';
import RevenueChart from '../../components/admin/Dashboard/RevenueChart';
import RecentTransactions from '../../components/admin/Financial/Transactions/RecentTransactions';
import PendingPayouts from '../../components/admin/Financial/Payouts/PendingPayouts';

const FinancialDashboardPage = () => {
    return (
        <div className="space-y-6">
            <h1 className="text-3xl font-bold">Tableau de Bord Financier</h1>
            
            <StatisticsCards
                stats={[
                    { label: 'Revenus du mois', value: '12 450 €', trend: '+12%' },
                    { label: 'Commissions', value: '2 890 €', trend: '+8%' },
                    { label: 'Versements en attente', value: '5 230 €' },
                    { label: 'Transactions', value: '156', trend: '+15%' },
                ]}
            />
            
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <RevenueChart />
                <PendingPayouts />
            </div>
            
            <RecentTransactions />
        </div>
    );
};

export default FinancialDashboardPage;
```

### 5. Hook Custom pour les Paiements
```javascript
// src/hooks/usePayment.js
import { useState } from 'react';
import { useDispatch } from 'react-redux';
import { toast } from 'react-toastify';
import { processPaymentAPI } from '../api/payment.api';
import { addPayment } from '../store/slices/financialSlice';

export const usePayment = () => {
    const [loading, setLoading] = useState(false);
    const dispatch = useDispatch();

    const processPayment = async (bookingId, paymentMethodId) => {
        setLoading(true);
        try {
            const response = await processPaymentAPI({
                bookingId,
                paymentMethodId,
            });
            
            dispatch(addPayment(response.data));
            toast.success('Paiement effectué avec succès !');
            return response.data;
        } catch (error) {
            toast.error(error.response?.data?.message || 'Erreur lors du paiement');
            throw error;
        } finally {
            setLoading(false);
        }
    };

    return { processPayment, loading };
};
```

### 6. Service API Payment
```javascript
// src/api/payment.api.js
import axios from './axios.config';

export const processPaymentAPI = (data) => {
    return axios.post('/client/financial/payments', data);
};

export const getPaymentsAPI = () => {
    return axios.get('/client/financial/payments');
};

export const getPaymentDetailsAPI = (id) => {
    return axios.get(`/client/financial/payments/${id}`);
};

export const requestRefundAPI = (paymentId, reason) => {
    return axios.post('/client/financial/refunds/request', {
        paymentId,
        reason,
    });
};
```

---

## Configuration Vite (Frontend Web)

```javascript
// vite.config.js
import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import path from 'path';

export default defineConfig({
    plugins: [react()],
    resolve: {
        alias: {
            '@': path.resolve(__dirname, './src'),
            '@components': path.resolve(__dirname, './src/components'),
            '@pages': path.resolve(__dirname, './src/pages'),
            '@api': path.resolve(__dirname, './src/api'),
            '@hooks': path.resolve(__dirname, './src/hooks'),
            '@utils': path.resolve(__dirname, './src/utils'),
            '@store': path.resolve(__dirname, './src/store'),
        },
    },
    server: {
        port: 3000,
        proxy: {
            '/api': {
                target: 'http://localhost:8000',
                changeOrigin: true,
            },
        },
    },
});
```

---

## Configuration Tailwind CSS

```javascript
// tailwind.config.js
/** @type {import('tailwindcss').Config} */
export default {
    content: [
        "./index.html",
        "./src/**/*.{js,ts,jsx,tsx}",
    ],
    theme: {
        extend: {
            colors: {
                primary: {
                    50: '#f0f9ff',
                    100: '#e0f2fe',
                    500: '#0ea5e9',
                    600: '#0284c7',
                    700: '#0369a1',
                },
                secondary: {
                    50: '#fdf4ff',
                    100: '#fae8ff',
                    500: '#d946ef',
                    600: '#c026d3',
                    700: '#a21caf',
                },
            },
        },
    },
    plugins: [
        require('@tailwindcss/forms'),
        require('@tailwindcss/typography'),
    ],
};
```



### Frontend Web (React.js)

```json
{
    "dependencies": {
        "react": "^18.2.0",
        "react-dom": "^18.2.0",
        "react-router-dom": "^6.20.1",
        "@reduxjs/toolkit": "^2.0.1",
        "react-redux": "^9.0.4",
        "axios": "^1.6.2",
        
        "@stripe/stripe-js": "^2.4.0",
        "@stripe/react-stripe-js": "^2.4.0",
        
        "react-hook-form": "^7.49.2",
        "yup": "^1.3.3",
        "@hookform/resolvers": "^3.3.3",
        
        "recharts": "^2.10.3",
        "date-fns": "^3.0.6",
        "react-datepicker": "^4.25.0",
        "react-select": "^5.8.0",
        "react-toastify": "^9.1.3",
        
        "tailwindcss": "^3.4.0",
        "@headlessui/react": "^1.7.17",
        "@heroicons/react": "^2.1.1",
        
        "framer-motion": "^10.16.16",
        "react-spinners": "^0.13.8"
    },
    "devDependencies": {
        "@types/react": "^18.2.45",
        "@types/react-dom": "^18.2.18",
        "@vitejs/plugin-react": "^4.2.1",
        "vite": "^5.0.8",
        "autoprefixer": "^10.4.16",
        "postcss": "^8.4.32",
        "eslint": "^8.56.0",
        "prettier": "^3.1.1"
    }
}
```

### Frontend Mobile (React Native)

```json
{
    "dependencies": {
        "react": "18.2.0",
        "react-native": "0.73.0",
        "@react-navigation/native": "^6.1.9",
        "@react-navigation/stack": "^6.3.20",
        "@reduxjs/toolkit": "^2.0.1",
        "react-redux": "^9.0.4",
        "axios": "^1.6.2",
        "@stripe/stripe-react-native": "^0.35.0",
        "react-native-push-notification": "^8.1.1",
        "react-native-maps": "^1.10.0",
        "react-native-calendar-picker": "^7.1.3",
        "formik": "^2.4.5",
        "yup": "^1.3.3"
    }
}
```

---

## Avantages de cette Architecture

### ✅ Module Financial Autonome

1. **Isolation complète** : Le module Financial ne dépend pas du reste de l'application
2. **Réutilisabilité** : Peut être extrait et utilisé dans d'autres projets
3. **Maintenabilité** : Code financier centralisé et facile à maintenir
4. **Testabilité** : Tests isolés du reste de l'application
5. **Scalabilité** : Peut être déployé séparément si nécessaire (microservice)

### ✅ Architecture Globale

1. **Séparation claire des responsabilités**
2. **Structure modulaire et évolutive**
3. **Pattern CQRS pour les opérations complexes**
4. **Event-driven architecture pour la communication**
5. **API RESTful bien structurée**
6. **Support mobile natif (React Native)**

---

## Prochaines Étapes de Développement

1. ✅ **Architecture définie**
2. 🔄 **Créer les entités principales**
3. 🔄 **Implémenter le module Financial**
4. 🔄 **Développer les services métier**
5. 🔄 **Créer les controllers et API**
6. 🔄 **Implémenter l'authentification JWT**
7. 🔄 **Développer l'application mobile**
8. 🔄 **Tests unitaires et d'intégration**
9. 🔄 **Déploiement**

---

**Cette architecture complète permet un développement structuré, maintenable et évolutif de la plateforme.**