<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    CheckCircle2,
    Clock,
    CreditCard,
    FileSpreadsheet,
    Flame,
    Key,
    Lock,
    Server,
    Shield,
    ShieldCheck,
    Sparkles,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { dashboard, login } from '@/routes';

const page = usePage();
const dashboardUrl = computed(() =>
    page.props.currentTeam
        ? dashboard(page.props.currentTeam.slug).url
        : '/dashboard',
);
</script>

<template>
    <Head title="VaultGuard — Zero-Knowledge Password & Credential Manager">
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link
            rel="preconnect"
            href="https://fonts.gstatic.com"
            crossorigin="anonymous"
        />
        <link
            href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;700&display=swap"
            rel="stylesheet"
        />
    </Head>

    <div
        class="min-h-screen bg-background font-['Plus_Jakarta_Sans',sans-serif] text-foreground selection:bg-primary/20 selection:text-primary"
    >
        <!-- Background Ambient Glows -->
        <div class="pointer-events-none fixed inset-0 -z-10 overflow-hidden">
            <div
                class="absolute -top-40 left-1/2 h-[500px] w-[900px] -translate-x-1/2 rounded-full bg-primary/10 blur-[130px]"
            />
            <div
                class="absolute top-1/3 -left-40 h-[400px] w-[500px] rounded-full bg-emerald-500/10 blur-[120px]"
            />
            <div
                class="absolute right-0 bottom-10 h-[400px] w-[500px] rounded-full bg-purple-500/10 blur-[140px]"
            />
        </div>

        <!-- Navigation Bar -->
        <header
            class="sticky top-0 z-50 w-full border-b border-border/40 bg-background/80 backdrop-blur-md"
        >
            <div
                class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8"
            >
                <div class="flex items-center gap-3">
                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary text-primary-foreground shadow-sm"
                    >
                        <ShieldCheck class="h-6 w-6" />
                    </div>
                    <div>
                        <span
                            class="text-lg font-bold tracking-tight text-foreground"
                            >VaultGuard</span
                        >
                        <span
                            class="ml-2 rounded-full border border-emerald-500/20 bg-emerald-500/10 px-2 py-0.5 text-[10px] font-semibold text-emerald-500"
                        >
                            AES-256
                        </span>
                    </div>
                </div>

                <nav class="flex items-center gap-3">
                    <template v-if="$page.props.auth.user">
                        <Link :href="dashboardUrl">
                            <Button class="gap-2">
                                <span>Go to Vault</span>
                                <ArrowRight class="h-4 w-4" />
                            </Button>
                        </Link>
                    </template>
                    <template v-else>
                        <Link :href="login()">
                            <Button variant="ghost" size="sm">Log in</Button>
                        </Link>
                        <Link href="/register">
                            <Button size="sm" class="gap-1.5 shadow-sm">
                                <span>Get Started</span>
                                <ArrowRight class="h-3.5 w-3.5" />
                            </Button>
                        </Link>
                    </template>
                </nav>
            </div>
        </header>

        <!-- Hero Section -->
        <main
            class="mx-auto max-w-7xl px-4 pt-16 pb-24 text-center sm:px-6 lg:px-8"
        >
            <!-- Pill Announcement -->
            <div
                class="animate-fade-in mb-8 inline-flex items-center gap-2 rounded-full border border-primary/20 bg-primary/10 px-3.5 py-1 text-xs font-medium text-primary"
            >
                <Sparkles class="h-3.5 w-3.5" />
                <span>Zero-Knowledge Architecture • Browser WebCrypto API</span>
            </div>

            <!-- Main Heading -->
            <h1
                class="mx-auto max-w-4xl text-4xl leading-[1.1] font-extrabold tracking-tight text-foreground sm:text-6xl lg:text-7xl"
            >
                Uncompromising Security for Your
                <span
                    class="bg-gradient-to-r from-primary via-emerald-500 to-primary bg-clip-text text-transparent"
                >
                    Personal & Team Secrets
                </span>
            </h1>

            <p
                class="mx-auto mt-6 max-w-2xl text-base leading-relaxed text-muted-foreground sm:text-lg"
            >
                Everything is encrypted in your browser using PBKDF2-SHA256 and
                AES-256-GCM before reaching the server. Only you hold the keys.
            </p>

            <!-- CTA Buttons -->
            <div
                class="mt-10 flex flex-col items-center justify-center gap-4 sm:flex-row"
            >
                <Link
                    :href="$page.props.auth.user ? dashboardUrl : '/register'"
                >
                    <Button
                        size="lg"
                        class="h-12 gap-2 px-8 text-base font-semibold shadow-md"
                    >
                        <ShieldCheck class="h-5 w-5" />
                        <span>{{
                            $page.props.auth.user
                                ? 'Open Your Vault'
                                : 'Create Free Vault'
                        }}</span>
                    </Button>
                </Link>
                <Link v-if="!$page.props.auth.user" :href="login()">
                    <Button
                        variant="outline"
                        size="lg"
                        class="h-12 px-8 text-base"
                    >
                        Sign In to Existing Vault
                    </Button>
                </Link>
            </div>

            <!-- Security Badges Ribbon -->
            <div
                class="mx-auto mt-16 grid max-w-4xl grid-cols-2 gap-4 text-left md:grid-cols-4"
            >
                <div
                    class="rounded-xl border border-border/60 bg-card/60 p-4 backdrop-blur-xs"
                >
                    <Key class="mb-2 h-5 w-5 text-primary" />
                    <h3 class="text-sm font-bold text-foreground">
                        Zero-Knowledge
                    </h3>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Master password never leaves your browser.
                    </p>
                </div>
                <div
                    class="rounded-xl border border-border/60 bg-card/60 p-4 backdrop-blur-xs"
                >
                    <Users class="mb-2 h-5 w-5 text-emerald-500" />
                    <h3 class="text-sm font-bold text-foreground">
                        Team Vaults
                    </h3>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Share credentials with granular role access.
                    </p>
                </div>
                <div
                    class="rounded-xl border border-border/60 bg-card/60 p-4 backdrop-blur-xs"
                >
                    <Clock class="mb-2 h-5 w-5 text-blue-500" />
                    <h3 class="text-sm font-bold text-foreground">
                        2FA Authenticator
                    </h3>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Built-in live 30s rotating TOTP codes.
                    </p>
                </div>
                <div
                    class="rounded-xl border border-border/60 bg-card/60 p-4 backdrop-blur-xs"
                >
                    <Flame class="mb-2 h-5 w-5 text-orange-500" />
                    <h3 class="text-sm font-bold text-foreground">
                        One-Time Links
                    </h3>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Self-destructing ephemeral secrets.
                    </p>
                </div>
            </div>

            <!-- Features Showcase Grid -->
            <div
                class="mx-auto mt-24 grid max-w-6xl grid-cols-1 gap-8 text-left md:grid-cols-3"
            >
                <div
                    class="rounded-2xl border border-border/70 bg-card p-6 shadow-sm transition-colors hover:border-primary/50"
                >
                    <div
                        class="mb-4 flex h-10 w-10 items-center justify-center rounded-xl bg-primary/10 text-primary"
                    >
                        <Key class="h-5 w-5" />
                    </div>
                    <h3 class="text-lg font-bold text-foreground">
                        Complete Credential Types
                    </h3>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Store websites, credit cards with CVV/PIN protection,
                        private notes, and SSH/API keys in one secure place.
                    </p>
                </div>

                <div
                    class="rounded-2xl border border-border/70 bg-card p-6 shadow-sm transition-colors hover:border-primary/50"
                >
                    <div
                        class="mb-4 flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-500"
                    >
                        <Shield class="h-5 w-5" />
                    </div>
                    <h3 class="text-lg font-bold text-foreground">
                        Security Health Audit
                    </h3>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Real-time vulnerability analysis highlights weak
                        passwords, reused credentials across sites, and accounts
                        missing 2FA.
                    </p>
                </div>

                <div
                    class="rounded-2xl border border-border/70 bg-card p-6 shadow-sm transition-colors hover:border-primary/50"
                >
                    <div
                        class="mb-4 flex h-10 w-10 items-center justify-center rounded-xl bg-purple-500/10 text-purple-500"
                    >
                        <FileSpreadsheet class="h-5 w-5" />
                    </div>
                    <h3 class="text-lg font-bold text-foreground">
                        Import & Export
                    </h3>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Seamlessly import or export from 1Password, Bitwarden,
                        and Google Chrome with client-side batch encryption.
                    </p>
                </div>
            </div>
        </main>

        <!-- Footer -->
        <footer
            class="border-t border-border/40 py-8 text-center text-xs text-muted-foreground"
        >
            <div
                class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-4 px-4 sm:flex-row"
            >
                <div class="flex items-center gap-2">
                    <ShieldCheck class="h-4 w-4 text-primary" />
                    <span class="font-semibold text-foreground"
                        >VaultGuard</span
                    >
                    <span>— Zero-Knowledge Security</span>
                </div>
                <div class="flex items-center gap-4">
                    <span>Client-Side AES-256-GCM</span>
                    <span>•</span>
                    <span>PBKDF2-SHA256</span>
                    <span>•</span>
                    <span>WebCrypto API</span>
                </div>
            </div>
        </footer>
    </div>
</template>
