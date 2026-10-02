<script setup lang="ts">
import {
    AlertTriangle,
    CheckCircle2,
    Clock,
    Copy,
    Key,
    Pencil,
    RefreshCw,
    Repeat,
    Shield,
    ShieldAlert,
    ShieldCheck,
    Sparkles,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { VaultItemData } from '@/composables/useVault';
import { calculatePasswordStrength } from '@/lib/crypto';

const props = defineProps<{
    items: VaultItemData[];
}>();

const emit = defineEmits<{
    (e: 'fixItem', item: VaultItemData): void;
}>();

const activeFilter = ref<'all' | 'weak' | 'reused' | 'missing2fa' | 'old'>(
    'all',
);

interface AuditedItem {
    item: VaultItemData;
    isWeak: boolean;
    isReused: boolean;
    isMissing2fa: boolean;
    isOld: boolean;
    reusedWithCount: number;
    strengthScore: number;
}

const auditData = computed(() => {
    // Tally password occurrences
    const passwordCounts = new Map<string, number>();
    props.items.forEach((it) => {
        const pwd = it.decrypted?.password;
        if (pwd) {
            passwordCounts.set(pwd, (passwordCounts.get(pwd) || 0) + 1);
        }
    });

    const now = Date.now();
    const ninetyDaysMs = 90 * 24 * 60 * 60 * 1000;

    const audited: AuditedItem[] = [];

    props.items.forEach((it) => {
        if (it.type !== 'login') return;

        const pwd = it.decrypted?.password || '';
        const strength = calculatePasswordStrength(pwd);
        const isWeak =
            pwd.length > 0 && (pwd.length < 12 || strength.score <= 2);
        const count = pwd ? passwordCounts.get(pwd) || 0 : 0;
        const isReused = count > 1;
        const isMissing2fa = !it.decrypted?.totp;

        const updatedAt = it.password_updated_at
            ? new Date(it.password_updated_at).getTime()
            : new Date(it.created_at).getTime();
        const isOld = now - updatedAt > ninetyDaysMs;

        audited.push({
            item: it,
            isWeak,
            isReused,
            isMissing2fa,
            isOld,
            reusedWithCount: count,
            strengthScore: strength.score,
        });
    });

    return audited;
});

const weakItems = computed(() => auditData.value.filter((a) => a.isWeak));
const reusedItems = computed(() => auditData.value.filter((a) => a.isReused));
const missing2faItems = computed(() =>
    auditData.value.filter((a) => a.isMissing2fa),
);
const oldItems = computed(() => auditData.value.filter((a) => a.isOld));

const overallScore = computed(() => {
    if (auditData.value.length === 0) return 100;
    const totalLogins = auditData.value.length;
    let penalties = 0;
    penalties += weakItems.value.length * 25;
    penalties += reusedItems.value.length * 20;
    penalties += missing2faItems.value.length * 10;
    penalties += oldItems.value.length * 5;

    const maxPenalty = totalLogins * 60;
    const raw = Math.round(100 - (penalties / maxPenalty) * 100);
    return Math.max(0, Math.min(100, raw));
});

const filteredList = computed(() => {
    switch (activeFilter.value) {
        case 'weak':
            return weakItems.value;
        case 'reused':
            return reusedItems.value;
        case 'missing2fa':
            return missing2faItems.value;
        case 'old':
            return oldItems.value;
        default:
            return auditData.value.filter(
                (a) => a.isWeak || a.isReused || a.isMissing2fa || a.isOld,
            );
    }
});
</script>

<template>
    <div class="space-y-6">
        <!-- Top Score Banner -->
        <div
            class="flex flex-col items-center justify-between gap-6 rounded-2xl border border-border/80 bg-gradient-to-r from-card via-card/80 to-muted/30 p-6 shadow-sm md:flex-row"
        >
            <div class="space-y-1 text-center md:text-left">
                <div
                    class="flex items-center justify-center gap-2 text-sm font-semibold text-primary md:justify-start"
                >
                    <ShieldCheck class="h-4 w-4" />
                    <span>Security Health Audit</span>
                </div>
                <h2 class="text-2xl font-bold tracking-tight text-foreground">
                    Vault Vulnerability Analysis
                </h2>
                <p class="max-w-xl text-sm text-muted-foreground">
                    Our real-time analyzer tests your decrypted vault against
                    weak passwords, reused credentials across different
                    services, and missing two-factor authentication.
                </p>
            </div>

            <!-- Health Score Ring -->
            <div
                class="flex shrink-0 items-center gap-4 rounded-2xl border border-border/60 bg-background/80 p-4 shadow-xs"
            >
                <div
                    class="relative flex h-20 w-20 items-center justify-center"
                >
                    <svg class="h-full w-full -rotate-90" viewBox="0 0 36 36">
                        <path
                            class="text-muted/40"
                            stroke-width="3.5"
                            stroke="currentColor"
                            fill="none"
                            d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                        />
                        <path
                            :class="[
                                overallScore >= 80
                                    ? 'text-emerald-500'
                                    : overallScore >= 50
                                      ? 'text-amber-500'
                                      : 'text-red-500',
                            ]"
                            stroke-dasharray="100, 100"
                            :stroke-dashoffset="100 - overallScore"
                            stroke-linecap="round"
                            stroke-width="3.5"
                            stroke="currentColor"
                            fill="none"
                            d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                        />
                    </svg>
                    <div class="absolute flex flex-col items-center">
                        <span class="text-2xl font-black text-foreground"
                            >{{ overallScore }}%</span
                        >
                    </div>
                </div>
                <div>
                    <span
                        class="text-xs font-medium tracking-wider text-muted-foreground uppercase"
                        >Health Status</span
                    >
                    <h3 class="text-base font-bold text-foreground">
                        {{
                            overallScore >= 90
                                ? 'Excellent'
                                : overallScore >= 75
                                  ? 'Good Protection'
                                  : overallScore >= 50
                                    ? 'Needs Attention'
                                    : 'At Risk'
                        }}
                    </h3>
                    <p class="text-xs text-muted-foreground">
                        {{ auditData.length }} credentials analyzed
                    </p>
                </div>
            </div>
        </div>

        <!-- Metric Stat Cards -->
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <!-- Weak -->
            <button
                type="button"
                class="rounded-xl border p-4 text-left transition-all hover:shadow-sm"
                :class="
                    activeFilter === 'weak'
                        ? 'border-red-500/80 bg-red-500/10'
                        : 'border-border/60 bg-card hover:bg-muted/30'
                "
                @click="activeFilter = activeFilter === 'weak' ? 'all' : 'weak'"
            >
                <div class="flex items-center justify-between">
                    <span
                        class="text-xs font-semibold text-muted-foreground uppercase"
                        >Weak Passwords</span
                    >
                    <ShieldAlert class="h-4 w-4 text-red-500" />
                </div>
                <div class="mt-2 text-2xl font-extrabold text-foreground">
                    {{ weakItems.length }}
                </div>
                <p class="mt-1 text-xs text-muted-foreground">
                    Low entropy or &lt; 12 chars
                </p>
            </button>

            <!-- Reused -->
            <button
                type="button"
                class="rounded-xl border p-4 text-left transition-all hover:shadow-sm"
                :class="
                    activeFilter === 'reused'
                        ? 'border-amber-500/80 bg-amber-500/10'
                        : 'border-border/60 bg-card hover:bg-muted/30'
                "
                @click="
                    activeFilter = activeFilter === 'reused' ? 'all' : 'reused'
                "
            >
                <div class="flex items-center justify-between">
                    <span
                        class="text-xs font-semibold text-muted-foreground uppercase"
                        >Reused Passwords</span
                    >
                    <Repeat class="h-4 w-4 text-amber-500" />
                </div>
                <div class="mt-2 text-2xl font-extrabold text-foreground">
                    {{ reusedItems.length }}
                </div>
                <p class="mt-1 text-xs text-muted-foreground">
                    Shared across multiple sites
                </p>
            </button>

            <!-- Missing 2FA -->
            <button
                type="button"
                class="rounded-xl border p-4 text-left transition-all hover:shadow-sm"
                :class="
                    activeFilter === 'missing2fa'
                        ? 'border-blue-500/80 bg-blue-500/10'
                        : 'border-border/60 bg-card hover:bg-muted/30'
                "
                @click="
                    activeFilter =
                        activeFilter === 'missing2fa' ? 'all' : 'missing2fa'
                "
            >
                <div class="flex items-center justify-between">
                    <span
                        class="text-xs font-semibold text-muted-foreground uppercase"
                        >Missing 2FA</span
                    >
                    <Key class="h-4 w-4 text-blue-500" />
                </div>
                <div class="mt-2 text-2xl font-extrabold text-foreground">
                    {{ missing2faItems.length }}
                </div>
                <p class="mt-1 text-xs text-muted-foreground">
                    No TOTP authenticator set
                </p>
            </button>

            <!-- Aging -->
            <button
                type="button"
                class="rounded-xl border p-4 text-left transition-all hover:shadow-sm"
                :class="
                    activeFilter === 'old'
                        ? 'border-purple-500/80 bg-purple-500/10'
                        : 'border-border/60 bg-card hover:bg-muted/30'
                "
                @click="activeFilter = activeFilter === 'old' ? 'all' : 'old'"
            >
                <div class="flex items-center justify-between">
                    <span
                        class="text-xs font-semibold text-muted-foreground uppercase"
                        >Aging Passwords</span
                    >
                    <Clock class="h-4 w-4 text-purple-500" />
                </div>
                <div class="mt-2 text-2xl font-extrabold text-foreground">
                    {{ oldItems.length }}
                </div>
                <p class="mt-1 text-xs text-muted-foreground">
                    Unchanged for 90+ days
                </p>
            </button>
        </div>

        <!-- Issue List -->
        <div
            class="overflow-hidden rounded-xl border border-border/70 bg-card shadow-xs"
        >
            <div
                class="flex items-center justify-between border-b border-border/60 bg-muted/20 p-4"
            >
                <div class="flex items-center gap-2">
                    <h3 class="text-sm font-bold text-foreground">
                        {{
                            activeFilter === 'weak'
                                ? 'Weak Passwords'
                                : activeFilter === 'reused'
                                  ? 'Reused Passwords'
                                  : activeFilter === 'missing2fa'
                                    ? 'Accounts Without 2FA'
                                    : activeFilter === 'old'
                                      ? 'Aging Passwords'
                                      : 'Security Recommendations'
                        }}
                    </h3>
                    <Badge variant="outline" class="text-xs">{{
                        filteredList.length
                    }}</Badge>
                </div>
                <span class="text-xs text-muted-foreground"
                    >Click 'Strengthen' to generate new credentials</span
                >
            </div>

            <div
                v-if="filteredList.length === 0"
                class="space-y-2 p-8 text-center"
            >
                <CheckCircle2 class="mx-auto h-10 w-10 text-emerald-500" />
                <h4 class="text-base font-bold text-foreground">
                    No Vulnerabilities Detected
                </h4>
                <p class="mx-auto max-w-sm text-xs text-muted-foreground">
                    All audited accounts in this category adhere to strong
                    password guidelines!
                </p>
            </div>

            <div v-else class="divide-y divide-border/40">
                <div
                    v-for="aud in filteredList"
                    :key="aud.item.id"
                    class="flex flex-col justify-between gap-3 p-4 transition-colors hover:bg-muted/20 sm:flex-row sm:items-center"
                >
                    <div class="space-y-1">
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-bold text-foreground">{{
                                aud.item.decrypted?.title || aud.item.title
                            }}</span>
                            <span
                                v-if="aud.item.decrypted?.username"
                                class="font-mono text-xs text-muted-foreground"
                            >
                                ({{ aud.item.decrypted.username }})
                            </span>
                        </div>
                        <div class="flex flex-wrap items-center gap-2 text-xs">
                            <Badge
                                v-if="aud.isWeak"
                                variant="destructive"
                                class="py-0 text-[10px]"
                            >
                                Weak Password
                            </Badge>
                            <Badge
                                v-if="aud.isReused"
                                class="border-amber-500/20 bg-amber-500/10 py-0 text-[10px] text-amber-500"
                            >
                                Reused across {{ aud.reusedWithCount }} items
                            </Badge>
                            <Badge
                                v-if="aud.isMissing2fa"
                                variant="outline"
                                class="border-blue-500/20 py-0 text-[10px] text-blue-500"
                            >
                                Missing 2FA
                            </Badge>
                            <Badge
                                v-if="aud.isOld"
                                variant="outline"
                                class="border-purple-500/20 py-0 text-[10px] text-purple-500"
                            >
                                &gt; 90 Days Old
                            </Badge>
                        </div>
                    </div>

                    <Button
                        size="sm"
                        class="shrink-0 gap-1.5"
                        @click="emit('fixItem', aud.item)"
                    >
                        <Pencil class="h-3.5 w-3.5" />
                        <span>Strengthen</span>
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>
