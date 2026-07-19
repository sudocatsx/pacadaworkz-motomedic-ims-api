# Manual database recovery with Cloudflare R2

This production-testing setup keeps database recovery free, manual, and disposable:

- Laravel on Hostinger authorizes a user with the exact `Settings.Manage Database` permission and dispatches a workflow.
- GitHub Actions supplies `pg_dump` and `pg_restore`, which Hostinger shared hosting does not provide.
- A private Cloudflare R2 Standard bucket stores verified dumps, sidecar checksums, quota usage, and operation status.
- The React Database tab shows progress and history. It never receives R2 or GitHub credentials and never accepts an arbitrary restore upload.

The application allows five successful manual backups and ten restore executions per Asia/Manila calendar month. Storage is capped at five manual dumps plus five pre-restore safety dumps. Deleting a dump does not refund monthly usage.

## 1. Create the private R2 bucket

The labels below follow the current Cloudflare dashboard. Do this at the **account level**, not inside the settings for the Cloudflare Pages website.

### 1.1 Activate R2 if this account has never used it

1. Sign in to the Cloudflare dashboard and select the correct account.
2. In the left sidebar, open **Storage & databases**.
3. Select **R2**, then **Overview**.
4. If Cloudflare shows an R2 checkout or subscription screen, complete it. R2 includes free monthly usage, but Cloudflare requires the R2 subscription to be activated before it allows S3 API credentials to be created.

Stop when the page heading is **R2 object storage** and you can see a **Create bucket** button.

### 1.2 Create the bucket

From **Storage & databases > R2 > Overview**:

1. Select **Create bucket**.
2. For **Bucket name**, enter:

   ```text
   pacadaworkz-db-backups
   ```

   If that name is unavailable, add a short unique suffix. Bucket names must use lowercase letters, numbers, and hyphens.
3. For **Location**, choose **Asia-Pacific**. This is Cloudflare's APAC location hint; R2 does not currently offer a specific Singapore choice in this form.
4. For **Default storage class**, choose **Standard**.
5. Do not select an EU or FedRAMP jurisdiction.
6. Select **Create bucket**.

Record the exact bucket name. It becomes both of these values:

```dotenv
R2_BACKUP_BUCKET=pacadaworkz-db-backups
```

```text
GitHub secret R2_BUCKET = pacadaworkz-db-backups
```

### 1.3 Confirm the bucket is private

New R2 buckets are private by default, but verify it:

1. Open **R2 > Overview** and select `pacadaworkz-db-backups`.
2. Select the bucket's **Settings** tab.
3. Find **Public Access** or **Public Development URL**.
4. Confirm **Public Development URL** is **Disabled** or **Not allowed**.
5. Under **Custom Domains**, confirm there are no connected domains.
6. Do not configure bucket CORS for this backup system.

The application uses private, five-minute presigned download URLs. Do not enable the `r2.dev` development URL and do not attach a custom domain.

### 1.4 Create the S3 API credentials

Return to **Storage & databases > R2 > Overview**. Do not use **My Profile > API Tokens**; R2 S3 credentials are created from the R2 page.

1. Find the **Account Details** card.
2. Next to **API Tokens**, select **Manage**. In some accounts this button appears as **Manage R2 API tokens**.
3. Select **Create Account API token**. If that option is unavailable because you are not a Super Administrator, select **Create User API token** instead.
4. For **Token name**, enter `pacadaworkz-production-testing`.
5. Under **Permissions**, choose **Object Read & Write**.
6. Under the bucket/resource scope, choose **Apply to specific buckets only** and select `pacadaworkz-db-backups`.
7. Do not select **Admin Read & Write**; this application does not need permission to create or delete buckets.
8. Create the token.
9. On the result screen, immediately copy both:
   - **Access Key ID**
   - **Secret Access Key**

The Secret Access Key is displayed only once. The general Cloudflare API token value is not the value expected by Laravel or the AWS CLI.

For this production-testing environment, the same bucket-scoped Access Key ID and Secret Access Key can be stored in Hostinger and in the GitHub environment. Separate tokens can be introduced later if the client chooses a paid production setup.

### 1.5 Record the Account ID and S3 endpoint

On **R2 > Overview**, find **Account Details** and copy **Account ID**. Do not use a Zone ID, website ID, Pages project ID, bucket ID, or token ID.

Construct the S3 endpoint from that Account ID:

```text
https://ACCOUNT_ID.r2.cloudflarestorage.com
```

For example, if the Account ID shown by Cloudflare is `0123456789abcdef0123456789abcdef`, use:

```text
https://0123456789abcdef0123456789abcdef.r2.cloudflarestorage.com
```

Do not append the bucket name or `/database` to the endpoint.

At this point, you should have exactly these five R2 values:

| Value | Example | Destination |
| --- | --- | --- |
| Account ID | `012345...abcdef` | GitHub `R2_ACCOUNT_ID` |
| Bucket name | `pacadaworkz-db-backups` | Hostinger `R2_BACKUP_BUCKET` and GitHub `R2_BUCKET` |
| Access Key ID | value from token result | Hostinger `R2_BACKUP_ACCESS_KEY_ID` and GitHub `R2_ACCESS_KEY_ID` |
| Secret Access Key | value shown once | Hostinger `R2_BACKUP_SECRET_ACCESS_KEY` and GitHub `R2_SECRET_ACCESS_KEY` |
| S3 endpoint | `https://ACCOUNT_ID.r2.cloudflarestorage.com` | Hostinger `R2_BACKUP_ENDPOINT` |

`R2_BACKUP_REGION` remains `auto`. The **Asia-Pacific** selection made while creating the bucket does not change this SDK value.

### 1.6 Add the lifecycle rules

Open **R2 > Overview > pacadaworkz-db-backups > Settings**. Under **Object Lifecycle Rules**, select **Add rule** once for each row below.

For each rule:

1. Enter the **Rule name** shown below.
2. Limit the rule to objects with the listed **Prefix**. Include the trailing `/`.
3. Choose the action that expires or deletes objects after a number of days.
4. Enter the listed number of days.
5. Keep the rule enabled and select **Save changes**.

| Rule name | Prefix | Action | Age |
| --- | --- | --- | ---: |
| `expire-manual-backups` | `database/manual/` | Expire/delete objects | 90 days |
| `expire-safety-backups` | `database/safety/` | Expire/delete objects | 90 days |
| `expire-operation-status` | `database/operations/` | Expire/delete objects | 30 days |
| `expire-quota-history` | `database/control/` | Expire/delete objects | 400 days |

Do not add a transition to **Infrequent Access**. Keep Cloudflare's default rule for aborting incomplete multipart uploads.

Cloudflare may remove expired objects up to approximately 24 hours after their expiration time. The in-app five-manual and five-safety storage limits remain the primary free-tier controls.

### 1.7 Add the US$1 budget alert

Budget alerts are account-wide and informational; they do not stop R2 operations.

1. Open **Manage Account > Billing**.
2. Select **Billable Usage**.
3. Select **Create budget alert** or **Set Budget Alert**.
4. Use `R2 production-testing warning` as the alert name.
5. Set **Budget threshold (USD)** to `1.00`.
6. Select the email notification destination and create the alert.

On the current dashboard, the same action may also appear in the **Billable usage** card on the R2 Overview page. The alert applies to total usage-based spending for the Cloudflare account, not only this bucket.

### Cloudflare R2 completion checklist

Do not continue to GitHub or Hostinger until every item is true:

- The bucket exists in the correct Cloudflare account.
- Location is Asia-Pacific and default storage class is Standard.
- Public Development URL is disabled and no custom domain is attached.
- The token permission is Object Read & Write and is scoped only to this bucket.
- The Access Key ID and Secret Access Key have both been saved.
- The Account ID came from R2 Account Details.
- The four lifecycle rules are enabled with the exact prefixes above.
- The US$1 budget alert is configured, if Billable Usage is available for the account.

Official Cloudflare references: [Get started with R2](https://developers.cloudflare.com/r2/get-started/), [R2 authentication](https://developers.cloudflare.com/r2/api/tokens/), [data location hints](https://developers.cloudflare.com/r2/reference/data-location/), [public bucket controls](https://developers.cloudflare.com/r2/buckets/public-buckets/), [object lifecycle rules](https://developers.cloudflare.com/r2/buckets/object-lifecycles/), and [budget alerts](https://developers.cloudflare.com/billing/manage/budget-alerts/).

## 2. Configure GitHub Actions secrets and the deployment environment

`production-testing` is **not** a default GitHub environment. It is a repository-level deployment environment explicitly selected by these jobs:

```yaml
environment: production-testing
```

The declaration is in both `database-backup.yml` and `database-restore.yml`. It labels their deployments and allows optional environment protection rules. It is unrelated to Laravel's `APP_ENV`, the API `.env` file, and Cloudflare Pages environments.

### 2.1 Add the secrets at repository level

For this organization-owned repository, the existing repository-secret location is correct:

1. Open the **API repository** on GitHub.
2. Select **Settings**.
3. Open **Secrets and variables > Actions**.
4. Select the **Secrets** tab.
5. Under **Repository secrets**, select **New repository secret**.
6. Add the database and R2 secrets required for backup:

```text
SUPABASE_DB_URL
R2_ACCOUNT_ID
R2_ACCESS_KEY_ID
R2_SECRET_ACCESS_KEY
R2_BUCKET
```

7. If the in-app **Restore** operation will be enabled, also add these Hostinger SSH secrets:

```text
HOSTINGER_SSH_HOST
HOSTINGER_SSH_PORT
HOSTINGER_SSH_USER
HOSTINGER_SSH_PRIVATE_KEY
HOSTINGER_APP_PATH
```

The five Hostinger values are not used by the FTP deployment workflow and are not needed to create or download backups. They are used only by `database-restore.yml` to:

1. Run `php artisan down` before replacing database data, preventing users from writing to the database during the restore.
2. Run `php artisan optimize:clear`, `php artisan migrate --force`, and `php artisan config:cache` after `pg_restore` completes.
3. Run `php artisan up` even when a later restore step fails, so the API is not accidentally left in maintenance mode.

The PostgreSQL restore itself runs from the GitHub runner directly against Supabase using `SUPABASE_DB_URL`. SSH is only the control connection to the Laravel application on Hostinger. This is separate from `.github/workflows/deploy.yml`, which continues to deploy files through FTP.

If you configure only the first five secrets, backup creation and R2 downloads can work, but database restore will fail when it reaches the Hostinger maintenance step. Do not expose the Restore action to production-testing users until the SSH values are configured and tested.

These repository secrets are available to the two jobs even though the jobs declare `environment: production-testing`. Do not duplicate the same secret names as environment secrets. If a repository secret and an environment secret have the same name, the environment secret takes precedence, which can make later troubleshooting confusing.

Being stored in an organization-owned repository does not turn these into organization secrets. Repository secrets belong only to this API repository. As an organization owner with repository admin access, you can manage both its repository secrets and environments.

Only use **Organization settings > Secrets and variables > Actions** if you deliberately want a secret shared by multiple repositories. When using an organization secret, its repository access policy must include this API repository. For the database and R2 credentials in this guide, repository secrets are the simpler and narrower choice.

The R2 values map as follows:

| GitHub secret | Cloudflare value |
| --- | --- |
| `R2_ACCOUNT_ID` | Account ID from **R2 > Overview > Account Details** |
| `R2_ACCESS_KEY_ID` | Access Key ID from the R2 token result screen |
| `R2_SECRET_ACCESS_KEY` | Secret Access Key from the R2 token result screen |
| `R2_BUCKET` | Exact bucket name, for example `pacadaworkz-db-backups` |

#### Create `SUPABASE_DB_URL`

This secret is required. The GitHub runners use it to connect directly to PostgreSQL while running `pg_dump` and `pg_restore`.

1. Open the Supabase project.
2. Select **Connect** at the top of the project dashboard.
3. Find the PostgreSQL connection strings and select **Session pooler**.
4. Copy the URI that uses the pooler hostname and port `5432`. Do not use **Transaction pooler**, which uses port `6543`.
5. Replace `[YOUR-PASSWORD]` with the project's database password. Percent-encode reserved characters in the password before placing it in the URI. For example, `@` becomes `%40`, `#` becomes `%23`, `%` becomes `%25`, and `/` becomes `%2F`.
6. Add `?sslmode=require` to the end of the URI. If the copied URI already contains a `?`, append `&sslmode=require` instead.
7. In the API repository, return to **Settings > Secrets and variables > Actions > Repository secrets**.
8. Create a repository secret named exactly `SUPABASE_DB_URL` and paste only the completed URI as its value.

The final shape should be:

```text
postgresql://postgres.PROJECT_REF:URL_ENCODED_DATABASE_PASSWORD@aws-0-REGION.pooler.supabase.com:5432/postgres?sslmode=require
```

Do not paste `SUPABASE_DB_URL=` before the value. Do not use the Supabase project URL, anon key, service-role key, transaction-pooler URL, or a browser-facing API URL. Never place this URI in a GitHub variable, workflow YAML file, committed `.env` file, Cloudflare Pages variable, issue, or workflow log because it contains the database password.

The Session pooler is used here because it supports IPv4, while Supabase's free direct database endpoint is normally IPv6 and is not suitable for GitHub-hosted Actions runners. `HOSTINGER_APP_PATH` is the absolute directory containing `artisan`.

### 2.2 Create or verify the `production-testing` environment

1. In the same API repository, open **Settings > Environments**.
2. If `production-testing` already exists, open it. GitHub may have created it automatically when one of the workflows first referenced it.
3. Otherwise, select **New environment**.
4. Enter the exact name `production-testing`, then select **Configure environment**.
5. Leave **Environment secrets** empty because this setup uses the repository secrets from Section 2.1.
6. Do not configure **Required reviewers** for this production-testing setup. Requiring approval would pause every API-triggered backup or restore until someone manually approves the GitHub job.
7. If **Deployment branches and tags** is available, restrict it to the repository's default `master` branch. Otherwise, leave the environment without protection rules.

The environment name can be changed, but both workflow files must be updated to the same new name. There is no special GitHub environment called `production-testing`; that is simply the name chosen for this deployment.

### 2.3 Create the Hostinger SSH secrets for restore

The restore workflow does not accept an SSH password. Create a dedicated SSH key pair for GitHub Actions, add only the public key to Hostinger, and store the private key as a GitHub repository secret.

#### Find the Hostinger connection values

1. In hPanel, open **Websites**.
2. Select **Dashboard** or **Manage** for `pacadaworkz.bscs3a.com`.
3. In the sidebar, search for and open **SSH Access** under **Advanced**.
4. If SSH status is disabled, select **Enable**.
5. Find the displayed SSH command. It normally has this shape:

   ```text
   ssh -p 65002 u123456789@123.123.123.123
   ```

6. Map the command to GitHub secrets:

   | Part of the SSH command | GitHub secret |
   | --- | --- |
   | IP address or hostname after `@` | `HOSTINGER_SSH_HOST` |
   | Number after `-p` | `HOSTINGER_SSH_PORT` |
   | Username before `@` | `HOSTINGER_SSH_USER` |

Use the SSH host displayed by Hostinger, not `pacadaworkz.bscs3a.com`, unless Hostinger's own SSH command explicitly uses that domain.

#### Generate a dedicated key pair

On Linux, macOS, or WSL, run this on your own computer—not in the Hostinger terminal and not in GitHub Actions:

```bash
ssh-keygen -t ed25519 -C "github-actions-pacadaworkz-db-restore" -f ~/.ssh/pacadaworkz_github_actions -N ""
```

On Windows PowerShell, run the interactive form below. When it asks for a passphrase and confirmation, press Enter twice to leave the dedicated automation key without a passphrase:

```powershell
ssh-keygen -t ed25519 -C "github-actions-pacadaworkz-db-restore" -f "$HOME\.ssh\pacadaworkz_github_actions"
```

This creates two different files:

| Local file | Purpose | Where it goes |
| --- | --- | --- |
| `~/.ssh/pacadaworkz_github_actions.pub` | Public key; safe to register as an authorized key | Hostinger hPanel |
| `~/.ssh/pacadaworkz_github_actions` | Private key; secret credential | GitHub `HOSTINGER_SSH_PRIVATE_KEY` |

The empty passphrase is intentional for this dedicated automation key because the non-interactive workflow has no person available to enter a passphrase. Do not reuse your normal personal SSH key.

#### Add the public key to Hostinger

1. On your computer, display the public key:

   ```bash
   cat ~/.ssh/pacadaworkz_github_actions.pub
   ```

2. Copy the entire single line beginning with `ssh-ed25519`.
3. Return to **hPanel > Websites > Dashboard > SSH Access**.
4. Scroll to the SSH keys section and select **Add SSH key**.
5. Enter `GitHub Actions database restore` as its name.
6. Paste the public key and select **Add SSH key**.

Never paste the private-key file into Hostinger's **Add SSH key** form.

#### Test the key from your computer

Replace the placeholders with the same host, port, and username shown in hPanel:

```bash
ssh -i ~/.ssh/pacadaworkz_github_actions -p HOSTINGER_PORT HOSTINGER_USER@HOSTINGER_HOST 'cd /home/u896434489/domains/pacadaworkz.bscs3a.com && php artisan --version'
```

Accept the host fingerprint on the first connection. The command must print the Laravel version without requesting the Hostinger SSH password. If it still asks for the password, stop and verify that the `.pub` public key was added to the same hosting account shown by the SSH command.

#### Add the remaining GitHub repository secrets

In **API repository > Settings > Secrets and variables > Actions > Repository secrets**, create:

| Secret | Value |
| --- | --- |
| `HOSTINGER_SSH_HOST` | Host/IP from hPanel's SSH command |
| `HOSTINGER_SSH_PORT` | Port from hPanel's SSH command, commonly `65002` |
| `HOSTINGER_SSH_USER` | Username from hPanel's SSH command, such as `u896434489` |
| `HOSTINGER_APP_PATH` | `/home/u896434489/domains/pacadaworkz.bscs3a.com` |
| `HOSTINGER_SSH_PRIVATE_KEY` | Entire contents of the private file `~/.ssh/pacadaworkz_github_actions` |

To copy the private value, open the extensionless private file locally:

```bash
cat ~/.ssh/pacadaworkz_github_actions
```

Paste every line, including `-----BEGIN OPENSSH PRIVATE KEY-----` and `-----END OPENSSH PRIVATE KEY-----`, into the GitHub secret. Do not add quotation marks, indentation, or the filename. Never paste this private key into chat, a repository file, Hostinger's public-key box, or a GitHub variable.

Both `.github/workflows/database-backup.yml` and `.github/workflows/database-restore.yml` must be committed to the repository's default `master` branch. Neither workflow has a schedule. Both share one concurrency group, so backup and restore cannot execute together.

## 3. Create the Hostinger GitHub token

This token lets the Laravel API on Hostinger call GitHub's workflow-dispatch API. Create it in your personal GitHub settings, but select the organization as its resource owner.

### 3.1 Generate the fine-grained token

1. Sign in to GitHub using the organization-owner account that has admin access to the API repository.
2. Select your profile picture in the upper-right corner, then **Settings**. This is your personal account's Settings page, not the repository or organization Settings page.
3. In the left sidebar, open **Developer settings**.
4. Open **Personal access tokens > Fine-grained tokens**.
5. Select **Generate new token** and complete GitHub's authentication confirmation if requested.
6. Configure these fields:

   | GitHub field | Value |
   | --- | --- |
   | Token name | `pacadaworkz-hostinger-database-dispatch` |
   | Description | `Hostinger API dispatches fixed database backup and restore workflows` |
   | Expiration | `90 days`, or the earliest date after the production-testing review |
   | Resource owner | The organization that owns `pacadaworkz-motomedic-ims-api` |
   | Repository access | **Only select repositories** |
   | Selected repositories | Only `pacadaworkz-motomedic-ims-api` |

7. Under **Permissions > Repository permissions**, find **Actions** and select **Read and write**.
8. Leave every other configurable repository, organization, and account permission at **No access**. **Metadata** remains **Read-only** automatically.
9. Review the summary: one organization, one repository, `Actions: Read and write`, and `Metadata: Read-only`.
10. Select **Generate token**.
11. Immediately copy the resulting value, which normally starts with `github_pat_`. GitHub will not show the complete token again.

The workflow-dispatch REST endpoint specifically requires `Actions: write`; this token does not require `Contents: write`, `Administration`, organization permissions, or a classic `repo` scope.

### 3.2 Organization approval, if shown

Fine-grained tokens targeting an organization can be subject to its token policy. A token created by an organization owner is normally approved automatically. If GitHub nevertheless marks it **Pending**:

1. Open the organization on GitHub.
2. Select **Settings**.
3. Under **Personal access tokens**, open **Pending requests**.
4. Open this token request, verify the one-repository scope and permissions, and approve it.

If the organization does not appear in the **Resource owner** list, open **Organization Settings > Personal access tokens > Settings > Fine-grained tokens** and confirm that fine-grained personal access tokens are allowed. An enterprise policy may prevent the organization from changing this setting.

### 3.3 Store the token in Hostinger

Edit the production Laravel `.env` file in the directory containing `artisan`. Add or update:

```dotenv
DATABASE_GITHUB_REPOSITORY=YOUR_ORGANIZATION/pacadaworkz-motomedic-ims-api
DATABASE_GITHUB_TOKEN=YOUR_COPIED_GITHUB_PAT
DATABASE_GITHUB_REF=master
DATABASE_GITHUB_API_URL=https://api.github.com
DATABASE_BACKUP_WORKFLOW=database-backup.yml
DATABASE_RESTORE_WORKFLOW=database-restore.yml
```

Replace `YOUR_ORGANIZATION` with the organization login from the repository URL, without `https://github.com/`. Paste the token after `DATABASE_GITHUB_TOKEN=` without quotes or spaces.

From the Hostinger terminal, in the directory containing `artisan`, rebuild Laravel's configuration cache:

```bash
php artisan optimize:clear
php artisan config:cache
```

Do not add this token to the GitHub Actions secrets created in Section 2. The direction is the opposite: Laravel on Hostinger uses it to request a GitHub workflow run. Store it only in Hostinger's Laravel `.env`; never place it in Cloudflare Pages, a `VITE_` variable, workflow YAML, a repository file, or a terminal command that would save it in shell history.

Create a reminder before the expiration date. When rotating the token, replace only `DATABASE_GITHUB_TOKEN` in Hostinger and rebuild the configuration cache. For a long-lived paid production deployment, replace this user-owned token with a narrowly scoped GitHub App installation token.

## 4. Configure the Hostinger API

Add these values to the production API `.env`:

```dotenv
DATABASE_BACKUP_DRIVER=github_r2
DATABASE_BACKUP_DISK=r2_backups
DATABASE_BACKUP_PREFIX=database
DATABASE_BACKUP_MONTHLY_LIMIT=5
DATABASE_RESTORE_MONTHLY_LIMIT=10
DATABASE_BACKUP_STORAGE_LIMIT=5
DATABASE_SAFETY_BACKUP_STORAGE_LIMIT=5
DATABASE_BACKUP_DOWNLOAD_TTL_MINUTES=5

R2_BACKUP_ACCESS_KEY_ID=YOUR_R2_ACCESS_KEY_ID
R2_BACKUP_SECRET_ACCESS_KEY=YOUR_R2_SECRET_ACCESS_KEY
R2_BACKUP_BUCKET=YOUR_PRIVATE_BUCKET
R2_BACKUP_ENDPOINT=https://YOUR_ACCOUNT_ID.r2.cloudflarestorage.com
R2_BACKUP_REGION=auto

DATABASE_GITHUB_REPOSITORY=OWNER/API_REPOSITORY
DATABASE_GITHUB_TOKEN=YOUR_FINE_GRAINED_TOKEN
DATABASE_GITHUB_REF=master
DATABASE_BACKUP_WORKFLOW=database-backup.yml
DATABASE_RESTORE_WORKFLOW=database-restore.yml
```

Deploy the updated `composer.json` and `composer.lock`. The FTP workflow deliberately excludes `vendor/` to keep deployments fast. Whenever `composer.lock` changes, install the locked production dependencies from the Hostinger terminal before rebuilding Laravel's configuration cache:

```bash
composer install --no-dev --optimize-autoloader
php artisan optimize:clear
php artisan config:cache
```

For the initial R2 deployment, the Composer command installs the required S3 adapter and AWS SDK into Hostinger's existing `vendor/`. Future FTP deployments do not need this step unless `composer.lock` changes. FTP cannot run Composer or Artisan commands itself.

### 4.1 Use this deployment order

1. Update the production Laravel `.env` on Hostinger with all R2 and GitHub values first. The currently deployed application will ignore configuration keys it does not yet use, and `.github/workflows/deploy.yml` excludes `.env`, so the FTP deployment will not overwrite them.
2. Commit the API code, `composer.json`, `composer.lock`, and both database workflow files.
3. Push the `dev` branch to trigger the existing FTP deployment.
4. Ensure the same commit is merged and pushed to the repository's default `master` branch. GitHub requires workflow-dispatch workflow files to exist on the default branch, and `DATABASE_GITHUB_REF=master` tells GitHub which revision to run. Do not test the Database tab before the two workflow files are present on `master`.
5. Wait for the FTP deployment to finish.
6. In the Hostinger terminal, run:

   ```bash
   cd /home/u896434489/domains/pacadaworkz.bscs3a.com
   composer install --no-dev --optimize-autoloader
   php artisan optimize:clear
   php artisan migrate --force
   php artisan config:cache
   ```

7. Verify **Settings > Database** and create a backup before testing restore.

The Composer setup and cache steps in `deploy.yml` run on the temporary GitHub runner. Because the FTP action deliberately excludes `vendor/`, those steps do not update Hostinger's `vendor/` directory. The one-time Hostinger `composer install` above is therefore required for this release, which adds `league/flysystem-aws-s3-v3`. Do not upload `vendor/` through FTP.

## 5. Verify backup and restore

1. Sign in with a role that has the exact `Settings.Manage Database` permission.
2. Open **Settings > Database**. The provider should show as configured and the quota counters should load.
3. Select **Create Backup**. The request returns immediately with a queued operation.
4. Wait for the operation to reach `succeeded`; follow **View run** if it fails.
5. Download the history item and confirm the signed URL expires shortly afterward.
6. For a restore test, select a verified history item, enter the current password, and type `RESTORE DATABASE`.
7. Confirm a pre-restore safety dump is added before the public schema is replaced, migrations complete, the API health check passes, and the user is signed out after success.

Never test restore for the first time against the only copy of important data. Keep one separately verified dump until the production-testing acceptance review is complete.

## Troubleshooting

- `backup_provider_unconfigured`: one or more R2 or GitHub API environment values are missing; run `php artisan optimize:clear` after correcting `.env`.
- `workflow_dispatch_failed`: check token expiration, repository selection, Actions permission, workflow filename, and that the workflow exists on `master`.
- `backup_storage_unavailable`: check the private bucket name, R2 endpoint, and bucket-scoped token permissions.
- An operation remains queued: open GitHub Actions and check environment approval rules. A queued record older than 20 minutes stops blocking a new request, but its workflow should still be investigated.
- Restore fails after maintenance mode: the workflow always attempts `php artisan up`; confirm Hostinger SSH values and run it manually if necessary.
