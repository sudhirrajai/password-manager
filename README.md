# VaultGuard — Zero-Knowledge Password Manager

A secure, zero-knowledge password and credentials management platform built with **Laravel 13**, **Inertia.js v3 (Vue 3)**, **Tailwind CSS v4**, and browser-native **WebCrypto API**.

---

## Key Features

- **Zero-Knowledge Architecture**: All sensitive credentials (passwords, notes, payment cards, TOTP secrets, server keys) are encrypted and decrypted directly in your browser using **`AES-256-GCM`** and **`PBKDF2-SHA256`** (100,000 iterations). Plaintext data is never transmitted to or stored on the server.
- **Personal & Team Vaults**: Isolated personal vaults alongside collaborative team vaults with role-based access control (Owner, Admin, Member, Read-Only).
- **Multiple Item Categories**:
  - **Logins**: Username, password, website URL, TOTP secret, and notes.
  - **Payment Cards**: Cardholder name, card number, expiration, CVV, and PIN with hide/reveal toggles.
  - **Secure Notes**: Confidential markdown notes, license keys, and recovery codes.
  - **Servers & APIs**: Host/IP, port, usernames, SSH private keys, and API tokens.
- **Live 2FA / TOTP Authenticator**: Built-in RFC 6238 TOTP authenticator with a 30-second countdown ring and 1-click copy.
- **Cryptographic Password & Passphrase Generator**:
  - Customizable random password generator (length, uppercase, lowercase, numbers, symbols, avoid ambiguous characters).
  - Word-based passphrase generator with custom separators.
  - Real-time Shannon entropy strength meter with crack-time estimation.
- **Security Health Center**: Real-time vault vulnerability audit detecting weak passwords, reused credentials across accounts, missing 2FA, and aging credentials.
- **Self-Destructing One-Time Secret Links**: Public links where the encryption key resides exclusively in the URL hash fragment (`#key=...`), which browsers never send to the server. Includes configurable view limits and expiry.
- **Import & Export**: Bitwarden and 1Password compatible CSV import/export, plus JSON backup.
- **Audit & Access Trail**: Comprehensive audit logs for view events, password copies, updates, and deletions.
- **Auto-Lock & Clipboard Security**: Auto-locks after 15 minutes of inactivity and automatically clears copied passwords from the system clipboard after 30 seconds.

---

## Tech Stack

- **Backend**: Laravel 13 (PHP 8.3+), Laravel Fortify, Laravel Wayfinder
- **Frontend**: Vue 3, Inertia.js v3, Tailwind CSS v4, Lucide Icons, Reka UI
- **Cryptography**: WebCrypto API (`PBKDF2`, `AES-GCM`, `HMAC-SHA1`)
- **Testing**: Pest PHP Test Suite

---

## Getting Started

### Prerequisites

- PHP 8.3+
- Composer
- Node.js 20+ & npm

### Installation

1. **Clone the repository**:
   ```bash
   git clone https://github.com/sudhirrajai/password-manager.git
   cd password-manager
   ```

2. **Install PHP and Node dependencies**:
   ```bash
   composer install
   npm install
   ```

3. **Configure Environment**:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Run Database Migrations**:
   ```bash
   php artisan migrate
   ```

5. **Generate Wayfinder Routes & Build Assets**:
   ```bash
   php artisan wayfinder:generate
   npm run build
   ```

6. **Start the Development Server**:
   ```bash
   php artisan serve
   ```
   and in a second terminal:
   ```bash
   npm run dev
   ```

7. Open [http://localhost:8000](http://localhost:8000) in your browser.

---

## Running Tests

Run the test suite with Pest:
```bash
./vendor/bin/pest
```

Run code formatting and type checks:
```bash
npm run check
npm run types:check
./vendor/bin/pint
```

---

## License

This project is open-source software licensed under the [MIT license](LICENSE).
