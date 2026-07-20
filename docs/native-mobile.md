# Mobile apps (Symfony UX Native / Hotwire Native)

Beacon can power **native iOS and Android apps** by wrapping the same Symfony + Twig UI in a [Hotwire Native](https://native.hotwired.dev/) shell. Server support comes from [`symfony/ux-native`](https://ux.symfony.com/native) (experimental) plus [`symfony/ux-turbo`](https://ux.symfony.com/turbo) for in-app navigation.

This is **not** a separate React Native / Flutter rewrite: the mobile app is a thin native shell + WebView that loads your Beacon instance (any domain / port).

Store client source trees are **not** shipped in this repository. You create the iOS/Android projects locally (or in a companion repo) and point them at your Beacon base URL.

## What the server already provides

| Piece | Role |
|-------|------|
| `symfony/ux-native` | Detects `Hotwire Native` User-Agent; `ux_is_native()` in Twig |
| `symfony/ux-turbo` | Turbo Drive for seamless WebView navigation |
| `src/Native/AppNativeConfiguration.php` | Path rules → `/config/ios_v1.json`, `/config/android_v1.json` |
| Layout tweaks | Hides PWA install chrome / brand duplication inside the native shell |
| `beacon-theme-bridge` Stimulus | Optional bridge to push light/dark theme to the native shell |

## Prerequisites

1. Beacon running and reachable from the device/simulator (HTTPS recommended).
2. A **base URL** for your instance, for example:
   - Local simulator (Mac host): `https://localhost:9444`
   - Physical device on LAN: `https://192.168.x.x:9444` (or a tunnel such as ngrok / Cloudflare Tunnel)
   - Production: `https://beacon.example.com`
3. For local self-signed TLS: trust the certificate on the simulator/device (or use a tunnel with a public CA certificate).

Native config endpoints (public):

| Platform | URL |
|----------|-----|
| iOS | `{BASE_URL}/config/ios_v1.json` |
| Android | `{BASE_URL}/config/android_v1.json` |

In **dev**, Symfony serves them dynamically. For **prod**, dump static files under `public/`:

```bash
docker compose exec -T php php bin/console ux:native:build-configs
```

Verify:

```bash
curl -sk https://localhost:9444/config/ios_v1.json
curl -sk https://localhost:9444/config/android_v1.json
```

---

## Create the iOS app

Official guide: [Hotwire Native iOS — Getting Started](https://native.hotwired.dev/ios/getting-started).

### 1. New Xcode project

1. Install **Xcode 15+**.
2. **File → New → Project…** → iOS **App**.
3. Product name e.g. `Beacon`, Language **Swift**, Interface **Storyboard**.
4. Save the project outside this Symfony repo (or in a dedicated companion repository).

### 2. Add Hotwire Native

1. **File → Add Package Dependencies…**
2. Package URL: `https://github.com/hotwired/hotwire-native-ios`
3. Add the package to your app target.

### 3. Point the shell at Beacon

Replace `SceneDelegate.swift` with a navigator whose `startLocation` is your Beacon base URL (login or dashboard):

```swift
import HotwireNative
import UIKit

// Change to your Beacon instance (host/port). Physical devices need a LAN IP or tunnel.
let rootURL = URL(string: "https://localhost:9444/en/login")!

class SceneDelegate: UIResponder, UIWindowSceneDelegate {
    var window: UIWindow?

    private let navigator = Navigator(configuration: .init(
        name: "main",
        startLocation: rootURL
    ))

    func scene(_ scene: UIScene, willConnectTo session: UISceneSession, options connectionOptions: UIScene.ConnectionOptions) {
        window?.rootViewController = navigator.rootViewController
        navigator.start()
    }
}
```

### 4. Load path configuration

1. Download a copy of `{BASE_URL}/config/ios_v1.json` and add it to the Xcode target as `path-configuration.json` (bundled fallback).
2. In `AppDelegate.swift`, load **local + remote** config before the first navigation:

```swift
import HotwireNative
import UIKit

@main
class AppDelegate: UIResponder, UIApplicationDelegate {
    func application(
        _ application: UIApplication,
        didFinishLaunchingWithOptions launchOptions: [UIApplication.LaunchOptionsKey: Any]?
    ) -> Bool {
        let localPathConfigURL = Bundle.main.url(
            forResource: "path-configuration",
            withExtension: "json"
        )!
        let remotePathConfigURL = URL(string: "https://localhost:9444/config/ios_v1.json")!

        Hotwire.loadPathConfiguration(from: [
            .file(localPathConfigURL),
            .server(remotePathConfigURL),
        ])

        return true
    }
}
```

Replace both URLs with your real base URL when leaving local development.

### 5. Run

**Product → Run**. Sign in with an existing Beacon account (`/en/login`). Session cookies work inside the WebView.

---

## Create the Android app

Official guide: [Hotwire Native Android — Getting Started](https://native.hotwired.dev/android/getting-started).

### 1. New Android Studio project

1. Install **Android Studio**.
2. **File → New → New Project…** → **Empty Views Activity**.
3. Minimum SDK **API 28+**, build language **Kotlin DSL**.
4. Save outside this Symfony repo (or in a companion repository).

### 2. Dependencies and Internet permission

In the **app** module `build.gradle.kts` (use the [latest release](https://github.com/hotwired/hotwire-native-android/releases)):

```kotlin
dependencies {
    implementation("dev.hotwire:core:<latest-version>")
    implementation("dev.hotwire:navigation-fragments:<latest-version>")
}
```

In `AndroidManifest.xml` (above `<application>`):

```xml
<uses-permission android:name="android.permission.INTERNET"/>
```

### 3. Layout host

Replace `activity_main.xml` with:

```xml
<?xml version="1.0" encoding="utf-8"?>
<androidx.fragment.app.FragmentContainerView
    xmlns:android="http://schemas.android.com/apk/res/android"
    xmlns:app="http://schemas.android.com/apk/res-auto"
    android:id="@+id/main_nav_host"
    android:name="dev.hotwire.navigation.navigator.NavigatorHost"
    android:layout_width="match_parent"
    android:layout_height="match_parent"
    app:defaultNavHost="false" />
```

### 4. Point the shell at Beacon

```kotlin
package com.example.beacon // update to match your project

import android.os.Bundle
import android.view.View
import androidx.activity.enableEdgeToEdge
import dev.hotwire.navigation.activities.HotwireActivity
import dev.hotwire.navigation.navigator.NavigatorConfiguration
import dev.hotwire.navigation.util.applyDefaultImeWindowInsets

class MainActivity : HotwireActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        enableEdgeToEdge()
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_main)
        findViewById<View>(R.id.main_nav_host).applyDefaultImeWindowInsets()
    }

    override fun navigatorConfigurations() = listOf(
        NavigatorConfiguration(
            name = "main",
            // Change to your Beacon instance (host/port). Emulator: 10.0.2.2 maps to host loopback.
            startLocation = "https://10.0.2.2:9444/en/login",
            navigatorHostId = R.id.main_nav_host
        )
    )
}
```

**Emulator tip:** `10.0.2.2` reaches the host machine’s `localhost`. A physical device needs your LAN IP or a tunnel.

### 5. Load path configuration

Follow [Android path configuration](https://native.hotwired.dev/android/path-configuration):

1. Bundle a local copy of `{BASE_URL}/config/android_v1.json` as a fallback asset.
2. Also load the remote URL `{BASE_URL}/config/android_v1.json` so Beacon can update rules without an app store release.

### 6. Run

**Run → Run ‘app’**. Sign in with a Beacon account. Session cookies work inside the WebView.

---

## Behaviour differences (web vs native)

When `ux_is_native()` is true:

- PWA manifest / install prompt are omitted (the store app replaces “Add to Home Screen”).
- Brand mark in the header is hidden (native navigation title usually covers branding).
- Breadcrumbs are hidden to save vertical space.
- Body gets `page-shell--native` (safe-area padding).

Path rules from Beacon (see `AppNativeConfiguration`):

- Envelope ingest URLs (`/api/.../envelope`) are not presented as navigable pages.
- Locale auth routes (`/en|es/login|register|logout`) open as a **modal**.
- Other app pages use the default stack with pull-to-refresh.

## Auth

Session cookie login still works inside the WebView (same AuthKit forms). For App Store / Play Store distribution you must still publish privacy / terms / legal notice pages (see [legal-and-cookies.md](legal-and-cookies.md)) and keep cookie consent for any non-essential SDKs (`nowo-tech/cookie-consent-bundle`).

## Local networking checklist

| Client | Typical Beacon URL |
|--------|--------------------|
| iOS Simulator | `https://localhost:9444` |
| Android Emulator | `https://10.0.2.2:9444` |
| Physical device | `https://<LAN-IP>:9444` or HTTPS tunnel |

If the WebView shows a TLS error, fix certificate trust or use a tunnel with a public certificate.

## Verify from the server side

```bash
# Simulate a Hotwire Native User-Agent
curl -sk -A 'Hotwire Native iOS' https://localhost:9444/en/login | head
curl -sk https://localhost:9444/config/ios_v1.json
curl -sk https://localhost:9444/config/android_v1.json
```

## Out of scope in this repository

- Shipping App Store / Play Store binaries or maintaining first-party Xcode / Android Studio trees here.
- Push notifications, camera, NFC, or a separate mobile-only REST API.
- React Native / Flutter rewrites of the dashboard.

## References

- [Symfony UX Native docs](https://symfony.com/bundles/ux-native/current/index.html)
- [Hotwire Native](https://native.hotwired.dev/)
- [Hotwire Native iOS getting started](https://native.hotwired.dev/ios/getting-started)
- [Hotwire Native Android getting started](https://native.hotwired.dev/android/getting-started)
- [Path configuration overview](https://native.hotwired.dev/overview/path-configuration)
- Spec: `specs/008-ux-native/`
- Beacon PWA (browser install): [`nowo-tech/pwa-bundle`](https://packagist.org/packages/nowo-tech/pwa-bundle)
