<?php
$feeTabPage = basename((string)($_SERVER['PHP_SELF'] ?? ''));
$feeTabTarget = static function (array $candidates, $fallback = null) {
    foreach ($candidates as $candidate) {
        if (is_file(__DIR__ . '/' . $candidate)) {
            return $candidate;
        }
    }
    return $fallback;
};

$feeTabs = [
    [
        'label' => 'Generate Fees',
        'icon' => 'fas fa-wand-magic-sparkles',
        // TODO: Plug getBillingRuleForClass() here when fee generation is refactored
        // CLASS BILLING OVERRIDE HOOK v1.0
        // To extend: add new fields to class_billing_rules table
        // and update getBillingRuleForClass() in includes/class_billing_functions.php
        // This hook will automatically pick up new fields without changing this file.
        'href' => $feeTabTarget(['generate_fees.php', 'generate-fees.php'], 'structure.php'),
        'pages' => ['generate_fees.php', 'generate-fees.php', 'structure.php'],
    ],
    [
        'label' => 'Collect Fee',
        'icon' => 'fas fa-money-bill-wave',
        'href' => $feeTabTarget(['collect_fee.php', 'collect-fee.php'], 'collect.php'),
        'pages' => ['collect_fee.php', 'collect-fee.php', 'collect.php', 'add_collection.php'],
    ],
    [
        'label' => 'Transactions',
        'icon' => 'fas fa-arrow-right-arrow-left',
        'href' => 'total_transactions.php',
        'pages' => ['total_transactions.php', 'total-transactions.php'],
    ],
    [
        'label' => 'Family Accounts',
        'icon' => 'fas fa-people-roof',
        'href' => $feeTabTarget(['family_accounts.php', 'family-accounts.php']),
        'pages' => ['family_accounts.php', 'family-accounts.php'],
    ],
    [
        'label' => 'Fee Records',
        'icon' => 'fas fa-file-invoice',
        'href' => $feeTabTarget(['fee_records.php', 'fee-records.php'], 'collections.php'),
        'pages' => ['fee_records.php', 'fee-records.php', 'collections.php'],
    ],
    [
        'label' => 'Reconcile Old',
        'icon' => 'fas fa-scale-balanced',
        'href' => $feeTabTarget(['reconcile_old.php', 'reconcile-old.php']),
        'pages' => ['reconcile_old.php', 'reconcile-old.php'],
    ],
    [
        'label' => 'Defaulters',
        'icon' => 'fas fa-triangle-exclamation',
        'href' => $feeTabTarget(['defaulters.php'], 'pending.php'),
        'pages' => ['defaulters.php', 'pending.php'],
    ],
];
?>

<ul class="nav nav-tabs mb-4" id="feeManagementTabs" role="tablist">
    <?php foreach ($feeTabs as $feeTab): ?>
        <?php $feeTabActive = in_array($feeTabPage, $feeTab['pages'], true); ?>
        <li class="nav-item" role="presentation">
            <?php if ($feeTab['href']): ?>
                <a class="nav-link<?php echo $feeTabActive ? ' active' : ''; ?>" href="<?php echo htmlspecialchars($feeTab['href']); ?>">
                    <i class="<?php echo htmlspecialchars($feeTab['icon']); ?> me-1"></i><?php echo htmlspecialchars($feeTab['label']); ?>
                </a>
            <?php else: ?>
                <span class="nav-link disabled" aria-disabled="true" title="This workspace is not available in the current fee module yet.">
                    <i class="<?php echo htmlspecialchars($feeTab['icon']); ?> me-1"></i><?php echo htmlspecialchars($feeTab['label']); ?>
                </span>
            <?php endif; ?>
        </li>
    <?php endforeach; ?>
</ul>
