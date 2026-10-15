<?php

declare(strict_types=1);

/**
 * Stable, human-selected Teacher referral reasons.
 *
 * These categories describe why the Teacher is submitting a referral. They do
 * not calculate or change a Student's intervention Tier.
 *
 * @return array<string, string>
 */
function aprismReferralReasonOptions(): array
{
    return [
        'frequent_absences' => 'Frequent Absences',
        'repeated_lateness' => 'Repeated Lateness',
        'low_academic_performance' => 'Low Academic Performance',
        'missing_late_requirements' => 'Missing or Late Requirements',
        'behavior_conduct' => 'Behavior / Conduct Concern',
        'other' => 'Other Concern',
    ];
}

function aprismReferralReasonIsValid(?string $reasonCode): bool
{
    return $reasonCode !== null
        && array_key_exists($reasonCode, aprismReferralReasonOptions());
}

function aprismReferralReasonLabel(?string $reasonCode): string
{
    $reasonCode = trim((string) $reasonCode);
    return aprismReferralReasonOptions()[$reasonCode] ?? 'Legacy / Unclassified';
}

/**
 * Return the human-selected reason with its optional note, or preserve an
 * older free-text concern under an explicit legacy label.
 */
function aprismReferralReasonDisplay(?string $reasonCode, ?string $note): string
{
    $note = trim((string) $note);
    $isLegacy = !aprismReferralReasonIsValid($reasonCode);
    $label = $isLegacy ? 'Legacy / Unclassified' : aprismReferralReasonLabel($reasonCode);

    if ($note === '') {
        return $label;
    }

    return $isLegacy ? $label . ': ' . $note : $label . ' — ' . $note;
}

function aprismTeacherReferralDecodeEvidenceSnapshot(?string $snapshotJson): ?array
{
    $snapshotJson = trim((string) $snapshotJson);
    if ($snapshotJson === '') {
        return null;
    }
    $decoded = json_decode($snapshotJson, true);
    return is_array($decoded) && (int) ($decoded['version'] ?? 0) === 1 ? $decoded : null;
}

function aprismTeacherReferralEvidenceSnapshotSummary(?string $snapshotJson): ?string
{
    $snapshot = aprismTeacherReferralDecodeEvidenceSnapshot($snapshotJson);
    if ($snapshot === null) {
        return null;
    }
    $capturedAt = '';
    try {
        if (trim((string) ($snapshot['captured_at'] ?? '')) !== '') {
            $capturedAt = (new DateTimeImmutable((string) $snapshot['captured_at']))
                ->setTimezone(new DateTimeZone('Asia/Manila'))
                ->format('M j, Y g:i A');
        }
    } catch (Throwable) {
        $capturedAt = '';
    }
    $prefix = $capturedAt !== '' ? 'At referral submission (' . $capturedAt . '): ' : 'At referral submission: ';
    if (empty($snapshot['assessable'])) {
        return $prefix . 'insufficient attendance evidence (' . max(0, (int) ($snapshot['recorded_count'] ?? 0)) . ' recorded sessions).';
    }
    $summary = 'Tier ' . max(1, (int) ($snapshot['tier'] ?? 1))
        . ' · ' . max(0, (int) ($snapshot['absent_count'] ?? 0)) . ' absences'
        . ' · ' . max(0, (int) ($snapshot['late_count'] ?? 0)) . ' lates'
        . ' · ' . max(0, (int) ($snapshot['effective_absent_count'] ?? 0)) . ' effective absences';
    if ((int) ($snapshot['consecutive_absence_count'] ?? 0) >= 3) {
        $summary .= ' · 3+ consecutive absences noted';
    }
    return $prefix . $summary . '.';
}
