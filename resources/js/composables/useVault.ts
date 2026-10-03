import { ref } from 'vue';
import { toast } from 'vue-sonner';
import {
    decryptData,
    decryptKeyWithKey,
    deriveKeyFromPassword,
    deriveKeyFromRecoveryKey,
    encryptData,
    encryptKeyWithKey,
    generateRandomSalt,
    generateRecoveryKey,
    generateVaultKey,
} from '@/lib/crypto';

// In-memory key storage (never written to localStorage or cookies for zero-knowledge safety)
const userVaultKey = ref<CryptoKey | null>(null);
const teamVaultKeys = ref<Map<number, CryptoKey>>(new Map());
const isUnlocked = ref(false);
const isConfigured = ref(true);
const hasRecoveryKey = ref(false);
const isTeamVault = ref(false);
const teamName = ref('');
const teamId = ref<number | null>(null);
const currentTeamSlug = ref('');
const autoLockTimer = ref<number | null>(null);
const autoLockMinutes = ref(15);

export interface DecryptedPayload {
    title: string;
    username?: string;
    password?: string;
    url?: string;
    notes?: string;
    totp?: string;
    // Card fields
    cardholder?: string;
    cardNumber?: string;
    expMonth?: string;
    expYear?: string;
    cvv?: string;
    pin?: string;
    // Server fields
    host?: string;
    port?: string;
    authType?: 'password' | 'key';
    sshPassword?: string;
    privateKey?: string;
    privateKeyFileName?: string;
    // Generic custom fields
    customFields?: Array<{ label: string; value: string; isSecret?: boolean }>;
}

export interface VaultItemData {
    id: number;
    team_id: number;
    user_id: number;
    type: 'login' | 'card' | 'note' | 'server';
    title: string;
    encrypted_data: string;
    iv: string;
    is_favorite: boolean;
    folder: string | null;
    last_used_at: string | null;
    password_updated_at: string | null;
    created_at: string;
    updated_at: string;
    deleted_at: string | null;
    user?: { id: number; name: string; email: string };
    decrypted?: DecryptedPayload;
}

export function useVault() {
    function resetAutoLockTimer() {
        if (autoLockTimer.value) {
            window.clearTimeout(autoLockTimer.value);
        }
        if (isUnlocked.value) {
            autoLockTimer.value = window.setTimeout(() => {
                lock();
                toast.info('Vault auto-locked due to inactivity.');
            }, autoLockMinutes.value * 60 * 1000);
        }
    }

    // Attach activity listeners
    if (typeof window !== 'undefined') {
        const events = ['mousedown', 'keydown', 'touchstart'];
        events.forEach((event) => {
            window.addEventListener(
                event,
                () => {
                    if (isUnlocked.value) resetAutoLockTimer();
                },
                { passive: true },
            );
        });
    }

    function setTeamContext(
        slug: string,
        id: number,
        name: string,
        isPersonal: boolean,
    ) {
        currentTeamSlug.value = slug;
        teamId.value = id;
        teamName.value = name;
        isTeamVault.value = !isPersonal;
    }

    async function checkVaultStatus(): Promise<{
        isConfigured: boolean;
        isUnlocked: boolean;
        hasRecoveryKey: boolean;
    }> {
        if (!currentTeamSlug.value)
            return {
                isConfigured: isConfigured.value,
                isUnlocked: isUnlocked.value,
                hasRecoveryKey: hasRecoveryKey.value,
            };

        try {
            const res = await fetch(`/${currentTeamSlug.value}/vault/key`, {
                headers: { Accept: 'application/json' },
            });
            if (res.ok) {
                const data = await res.json();
                isConfigured.value = data.is_configured;
                hasRecoveryKey.value = !!data.has_recovery_key;
                isTeamVault.value = data.is_team_vault;
                teamName.value = data.team_name;
            }
        } catch (e) {
            console.error('Failed to check vault status', e);
        }

        return {
            isConfigured: isConfigured.value,
            isUnlocked: isUnlocked.value,
            hasRecoveryKey: hasRecoveryKey.value,
        };
    }

    /**
     * Unlock vault using Master Password
     */
    async function unlock(masterPassword: string): Promise<boolean> {
        try {
            const res = await fetch(`/${currentTeamSlug.value}/vault/key`, {
                headers: { Accept: 'application/json' },
            });
            if (!res.ok)
                throw new Error('Failed to retrieve vault key configuration');

            const data = await res.json();
            if (!data.is_configured) {
                isConfigured.value = false;
                return false;
            }

            // Derive master key
            const masterKey = await deriveKeyFromPassword(
                masterPassword,
                data.vault_salt,
            );

            // Decrypt User Vault Key (UVK)
            const uvk = await decryptKeyWithKey(
                data.encrypted_vault_key,
                data.vault_key_iv,
                masterKey,
            );

            userVaultKey.value = uvk;
            hasRecoveryKey.value = !!data.has_recovery_key;

            // If team vault, decrypt Team Vault Key (TVK)
            if (data.is_team_vault && data.team_vault_key) {
                const tvk = await decryptKeyWithKey(
                    data.team_vault_key.encrypted_team_key,
                    data.team_vault_key.team_key_iv,
                    uvk,
                );
                if (teamId.value) {
                    teamVaultKeys.value.set(teamId.value, tvk);
                }
            }

            isUnlocked.value = true;
            resetAutoLockTimer();
            toast.success('Vault unlocked successfully.');
            return true;
        } catch (e) {
            console.error(e);
            toast.error('Invalid Master Password. Decryption failed.');
            return false;
        }
    }

    /**
     * Setup / Initialize new Vault Key with generated Recovery Key
     */
    async function setupVault(
        masterPassword: string,
    ): Promise<{ success: boolean; recoveryKey?: string }> {
        try {
            const salt = generateRandomSalt(32);
            const masterKey = await deriveKeyFromPassword(masterPassword, salt);
            const uvk = await generateVaultKey();
            const encryptedUvk = await encryptKeyWithKey(uvk, masterKey);

            // Generate Recovery Key
            const recovery = generateRecoveryKey();
            const recoverySalt = generateRandomSalt(32);
            const recoveryDerived = await deriveKeyFromRecoveryKey(
                recovery.rawKey,
                recoverySalt,
            );
            const encryptedRecovery = await encryptKeyWithKey(
                uvk,
                recoveryDerived,
            );

            let encryptedTeamKey: string | undefined;
            let teamKeyIv: string | undefined;

            if (isTeamVault.value) {
                const tvk = await generateVaultKey();
                const encTvk = await encryptKeyWithKey(tvk, uvk);
                encryptedTeamKey = encTvk.encryptedKey;
                teamKeyIv = encTvk.iv;
                if (teamId.value) {
                    teamVaultKeys.value.set(teamId.value, tvk);
                }
            }

            const csrfToken =
                (
                    document.querySelector(
                        'meta[name="csrf-token"]',
                    ) as HTMLMetaElement
                )?.content || '';

            const res = await fetch(`/${currentTeamSlug.value}/vault/key`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({
                    vault_salt: salt,
                    encrypted_vault_key: encryptedUvk.encryptedKey,
                    vault_key_iv: encryptedUvk.iv,
                    recovery_salt: recoverySalt,
                    encrypted_recovery_key: encryptedRecovery.encryptedKey,
                    recovery_key_iv: encryptedRecovery.iv,
                    encrypted_team_key: encryptedTeamKey,
                    team_key_iv: teamKeyIv,
                }),
            });

            if (!res.ok) {
                const err = await res.json();
                throw new Error(err.message || 'Setup failed');
            }

            userVaultKey.value = uvk;
            isConfigured.value = true;
            isUnlocked.value = true;
            hasRecoveryKey.value = true;
            resetAutoLockTimer();
            toast.success('Zero-knowledge vault initialized successfully!');
            return { success: true, recoveryKey: recovery.formattedKey };
        } catch (e: any) {
            toast.error(e.message || 'Failed to setup vault');
            return { success: false };
        }
    }

    /**
     * Recover vault password using Recovery Key
     */
    async function recoverVaultWithKey(
        recoveryKeyInput: string,
        newMasterPassword: string,
    ): Promise<{ success: boolean; newRecoveryKey?: string }> {
        try {
            const res = await fetch(`/${currentTeamSlug.value}/vault/key`, {
                headers: { Accept: 'application/json' },
            });
            if (!res.ok)
                throw new Error('Failed to retrieve vault configuration');

            const data = await res.json();
            if (!data.encrypted_recovery_key || !data.recovery_salt) {
                throw new Error('No recovery key found for this vault.');
            }

            // Derive key from entered recovery key
            const recoveryDerived = await deriveKeyFromRecoveryKey(
                recoveryKeyInput,
                data.recovery_salt,
            );

            // Decrypt User Vault Key (UVK)
            const uvk = await decryptKeyWithKey(
                data.encrypted_recovery_key,
                data.recovery_key_iv,
                recoveryDerived,
            );

            // Now re-encrypt UVK with new Master Password
            const newSalt = generateRandomSalt(32);
            const newMasterKey = await deriveKeyFromPassword(
                newMasterPassword,
                newSalt,
            );
            const newEncryptedUvk = await encryptKeyWithKey(uvk, newMasterKey);

            // Generate new Recovery Key
            const newRecovery = generateRecoveryKey();
            const newRecoverySalt = generateRandomSalt(32);
            const newRecoveryDerived = await deriveKeyFromRecoveryKey(
                newRecovery.rawKey,
                newRecoverySalt,
            );
            const newEncryptedRecovery = await encryptKeyWithKey(
                uvk,
                newRecoveryDerived,
            );

            const csrfToken =
                (
                    document.querySelector(
                        'meta[name="csrf-token"]',
                    ) as HTMLMetaElement
                )?.content || '';

            const saveRes = await fetch(
                `/${currentTeamSlug.value}/vault/key/recover`,
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({
                        new_vault_salt: newSalt,
                        new_encrypted_vault_key: newEncryptedUvk.encryptedKey,
                        new_vault_key_iv: newEncryptedUvk.iv,
                        new_recovery_salt: newRecoverySalt,
                        new_encrypted_recovery_key:
                            newEncryptedRecovery.encryptedKey,
                        new_recovery_key_iv: newEncryptedRecovery.iv,
                    }),
                },
            );

            if (!saveRes.ok) {
                const err = await saveRes.json();
                throw new Error(err.message || 'Recovery failed.');
            }

            userVaultKey.value = uvk;
            isConfigured.value = true;
            isUnlocked.value = true;
            hasRecoveryKey.value = true;
            resetAutoLockTimer();
            toast.success(
                'Master Password reset successfully with Recovery Key!',
            );
            return { success: true, newRecoveryKey: newRecovery.formattedKey };
        } catch (e: any) {
            console.error(e);
            toast.error(e.message || 'Invalid recovery key or recovery failed.');
            return { success: false };
        }
    }

    /**
     * Complete wipe/reset of vault if all passwords and recovery keys are lost
     */
    async function resetVaultWipe(): Promise<boolean> {
        const csrfToken =
            (
                document.querySelector(
                    'meta[name="csrf-token"]',
                ) as HTMLMetaElement
            )?.content || '';
        try {
            const res = await fetch(
                `/${currentTeamSlug.value}/vault/key/reset`,
                {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                },
            );
            if (res.ok) {
                lock();
                isConfigured.value = false;
                hasRecoveryKey.value = false;
                toast.success(
                    'Vault wiped successfully. You can now initialize a fresh vault.',
                );
                return true;
            }
            return false;
        } catch {
            toast.error('Failed to reset vault.');
            return false;
        }
    }

    /**
     * Rotate / generate a fresh emergency recovery key while vault is unlocked
     */
    async function rotateRecoveryKey(): Promise<{
        success: boolean;
        recoveryKey?: string;
    }> {
        if (!userVaultKey.value) {
            toast.error('Unlock your vault first to generate a recovery key.');
            return { success: false };
        }

        const csrfToken =
            (
                document.querySelector(
                    'meta[name="csrf-token"]',
                ) as HTMLMetaElement
            )?.content || '';

        try {
            const recoveryObj = generateRecoveryKey();
            const recoverySalt = generateRandomSalt(32);
            const recoveryDerived = await deriveKeyFromRecoveryKey(
                recoveryObj.rawKey,
                recoverySalt,
            );
            const encryptedRecovery = await encryptKeyWithKey(
                userVaultKey.value,
                recoveryDerived,
            );

            const res = await fetch(
                `/${currentTeamSlug.value}/vault/key/recovery-key`,
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({
                        recovery_salt: recoverySalt,
                        encrypted_recovery_key: encryptedRecovery.encryptedKey,
                        recovery_key_iv: encryptedRecovery.iv,
                    }),
                },
            );

            if (!res.ok) {
                const err = await res.json();
                throw new Error(err.message || 'Failed to update recovery key.');
            }

            hasRecoveryKey.value = true;
            toast.success('Emergency Recovery Key updated successfully!');
            return { success: true, recoveryKey: recoveryObj.formattedKey };
        } catch (e: any) {
            console.error(e);
            toast.error(e.message || 'Failed to generate new recovery key.');
            return { success: false };
        }
    }

    /**
     * Lock the vault immediately
     */
    function lock() {
        userVaultKey.value = null;
        teamVaultKeys.value.clear();
        isUnlocked.value = false;
        if (autoLockTimer.value) {
            window.clearTimeout(autoLockTimer.value);
            autoLockTimer.value = null;
        }
    }

    /**
     * Get the active encryption key for the current team context
     */
    function getActiveKey(): CryptoKey {
        if (
            isTeamVault.value &&
            teamId.value &&
            teamVaultKeys.value.has(teamId.value)
        ) {
            return teamVaultKeys.value.get(teamId.value)!;
        }
        if (!userVaultKey.value) {
            throw new Error(
                'Vault is locked. Unlock before encrypting/decrypting.',
            );
        }
        return userVaultKey.value;
    }

    /**
     * Encrypt an item payload before sending to backend
     */
    async function encryptItemPayload(
        payload: DecryptedPayload,
    ): Promise<{ ciphertext: string; iv: string }> {
        const key = getActiveKey();
        return encryptData(payload, key);
    }

    /**
     * Decrypt a vault item using the active key
     */
    async function decryptItem(
        item: VaultItemData,
    ): Promise<DecryptedPayload> {
        const key = getActiveKey();
        try {
            return await decryptData<DecryptedPayload>(
                item.encrypted_data,
                item.iv,
                key,
            );
        } catch {
            return {
                title: item.title,
                notes: '[Decryption Error: Key mismatch or corrupted data]',
            };
        }
    }

    /**
     * Decrypt a historical version payload using the active key
     */
    async function decryptHistoricalPayload(
        ciphertext: string,
        iv: string,
    ): Promise<DecryptedPayload> {
        const key = getActiveKey();
        try {
            return await decryptData<DecryptedPayload>(
                ciphertext,
                iv,
                key,
            );
        } catch {
            return {
                title: 'Historical Version',
                notes: '[Decryption Error: Key mismatch or corrupted data]',
            };
        }
    }

    /**
     * Copy to clipboard with auto-clear security timer (e.g. 30 seconds)
     */
    async function copyToClipboardWithAutoClear(
        text: string,
        label: string,
        timeoutSec = 30,
    ) {
        try {
            await navigator.clipboard.writeText(text);
            toast.success(`${label} copied to clipboard!`, {
                description: `Clipboard will auto-clear in ${timeoutSec} seconds.`,
            });

            setTimeout(async () => {
                try {
                    const currentText = await navigator.clipboard.readText();
                    if (currentText === text) {
                        await navigator.clipboard.writeText('');
                        toast.info(`Clipboard cleared for security (${label}).`);
                    }
                } catch {
                    // Browser permissions may restrict reading clipboard in background
                }
            }, timeoutSec * 1000);
        } catch {
            toast.error(`Failed to copy ${label} to clipboard.`);
        }
    }

    /**
     * Log an audit action to backend
     */
    async function auditAction(
        action: string,
        itemId?: number,
        itemTitle?: string,
    ) {
        if (!currentTeamSlug.value) return;
        const csrfToken =
            (
                document.querySelector(
                    'meta[name="csrf-token"]',
                ) as HTMLMetaElement
            )?.content || '';
        try {
            await fetch(`/${currentTeamSlug.value}/vault/items/audit`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({
                    vault_item_id: itemId,
                    action,
                    item_title: itemTitle,
                }),
            });
        } catch {
            // Ignore audit log failure
        }
    }

    return {
        isUnlocked,
        isConfigured,
        hasRecoveryKey,
        isTeamVault,
        teamName,
        teamId,
        currentTeamSlug,
        setTeamContext,
        checkVaultStatus,
        unlock,
        setupVault,
        recoverVaultWithKey,
        rotateRecoveryKey,
        resetVaultWipe,
        lock,
        getActiveKey,
        encryptItemPayload,
        decryptItem,
        decryptHistoricalPayload,
        copyToClipboardWithAutoClear,
        auditAction,
    };
}
