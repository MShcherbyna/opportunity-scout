# Concept: how Opportunity Scout works with Threads

- Created: 2026-10-22 20:09
- Status: draft
- Request: Describe the overall concept of the Threads integration and keyword search without technical details: whether each user connects their own account, how limits work, the user journey.
- Related: [Preparation plan](2026-10-22-1956-threads-api-preparation.md)

## 1. Each user connects their own Threads account

- **Limits.** Meta counts the search limit (2,200 queries per 24 hours) per Threads account. One shared account for everyone would run out fast. With their own account, each user gets their own quota.
- **Meta rules.** The search permission is granted to apps that act on behalf of a specific user and help them manage their social media presence. One "service" account for all users goes against this model and risks the app being blocked.
- **Reliability.** If Meta restricts one account, only that user is affected, not everyone.

The limit is per account, not per app. If a user also uses other Threads search tools, those spend the same quota.

```mermaid
flowchart LR
    subgraph Scout["Opportunity Scout"]
        U1["User A"]
        U2["User B"]
        U3["User C"]
    end
    U1 --> T1["Threads account A<br/>own quota: 2,200 / day"]
    U2 --> T2["Threads account B<br/>own quota: 2,200 / day"]
    U3 --> T3["Threads account C<br/>own quota: 2,200 / day"]
    T1 --> TH[("Threads")]
    T2 --> TH
    T3 --> TH
```

## 2. User journey

1. **Sign up** in Opportunity Scout.
2. **Connect Threads.** "Connect Threads" button → log in to Threads → allow search → back to the app. The account can be disconnected at any time.
3. **Set up search.** Keywords (e.g. "looking for a designer", "need a developer") and how often to check.
4. **Automatic search.** The app searches for new posts for each keyword on a schedule, on behalf of the user. The user doesn't have to do anything.
5. **Filtering.** The app drops duplicates and posts it has already seen, and the filter (including AI) removes irrelevant ones. Only potentially useful posts are saved.
6. **Result.** The user gets a notification about new finds and sees them on the dashboard with statistics. From there they open the original post in Threads and reply themselves, using the saved reply template.

```mermaid
flowchart TD
    A["Sign up"] --> B["Connect Threads account"]
    B --> C["Set keywords and check frequency"]
    C --> D["App searches on a schedule"]
    D --> E["Filter: duplicates, already seen, AI relevance"]
    E --> F["Save useful posts"]
    F --> G["Notification + dashboard"]
    G --> H["Open post in Threads and reply with a template"]
    D -. "next run" .-> D
```

## 3. One search cycle

```mermaid
sequenceDiagram
    participant S as Opportunity Scout
    participant T as Threads
    participant U as User
    loop For each keyword, on schedule
        S->>T: Search new posts (on behalf of the user)
        T-->>S: Matching posts
    end
    S->>S: Drop duplicates and seen posts, AI filter
    S->>S: Save relevant posts
    S-->>U: Notification about new finds
    U->>S: Review on dashboard
    U->>T: Open original post and reply
```

## 4. Limits in the UI

The number of keywords and the check frequency together spend the daily quota. So the app should:

- show how much of the quota the current settings use;
- not allow settings that would exceed it (e.g. 50 keywords every minute);
- if the quota still runs out, pause search until it recovers and tell the user why.

```mermaid
flowchart LR
    K["Keywords"] --> Q{"Fits the daily quota?"}
    F["Check frequency"] --> Q
    Q -- "Yes" --> OK["Save settings<br/>show quota usage"]
    Q -- "No" --> NO["Block and suggest<br/>fewer keywords or rarer checks"]
```

## 5. Connection state

- Access to Threads is valid for a limited time, and the app renews it automatically.
- If the connection breaks (the user revoked access in Threads, or it expired), search stops and the user is told to reconnect Threads.

```mermaid
stateDiagram-v2
    [*] --> NotConnected
    NotConnected --> Connected: User connects Threads
    Connected --> Connected: Access renewed automatically
    Connected --> QuotaPaused: Daily quota used up
    QuotaPaused --> Connected: Quota recovered
    Connected --> Broken: Access revoked or expired
    Broken --> Connected: User reconnects
    Connected --> NotConnected: User disconnects
```

Search runs only in the `Connected` state. In `QuotaPaused` and `Broken` the user sees why it stopped.

## 6. Launch stages

- **Before Meta approval**, search sees only the connected account's own posts. At this stage we work only with test accounts and build and check the whole flow.
- **After approval**, search covers all public posts, and real users can use the product.

```mermaid
flowchart LR
    S1["Stage 1: test accounts<br/>search only own posts<br/>build and check the flow"] --> R{"Meta App Review"}
    R -- "Approved" --> S2["Stage 2: real users<br/>search all public posts"]
    R -- "Rejected" --> FIX["Fix remarks and resubmit"] --> R
```
