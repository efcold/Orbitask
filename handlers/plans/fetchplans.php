<?php
$myPlans = $database
    ->getReference($plansRef)
    ->getValue() ?? [];

$invitationsRef = "invitations/{$myEmailKey}";
$invitations    = $database
    ->getReference($invitationsRef)
    ->getValue() ?? [];

$invitedPlans = [];
foreach ($invitations as $inv) {
    if (!empty($inv['plan_id']) && !empty($inv['owner'])) {
        $planDetail = $database
            ->getReference("users/{$inv['owner']}/plans/{$inv['plan_id']}")
            ->getValue();
        if ($planDetail) {
            $planDetail['owner']       = $inv['owner'];
            $planDetail['invited_role']= $inv['role'];
            $planDetail['plan_id']     = $inv['plan_id'];
            $invitedPlans[] = $planDetail;
        }
    }
}
$invitationsRaw = $database->getReference("invitations/{$myEmailKey}")->getValue();
$invitedPlans = [];

if (!empty($invitationsRaw)) {
    foreach ($invitationsRaw as $inviteKey => $invitation) {
        if (!empty($invitation['ignored'])) {
            continue; 
        }

        if (!empty($invitation['plan_id']) && !empty($invitation['owner'])) {
            $planDetail = $database
                ->getReference("users/{$invitation['owner']}/plans/{$invitation['plan_id']}")
                ->getValue();

            if ($planDetail) {
                $planDetail['owner']         = $invitation['owner'];
                $planDetail['invited_role']  = $invitation['role'];
                $planDetail['plan_id']       = $invitation['plan_id'];
                $planDetail['accepted']      = $invitation['accepted'] ?? false;
                $planDetail['invite_key']    = $inviteKey;
                $invitedPlans[] = $planDetail;
            }
        }
    }
}

?>