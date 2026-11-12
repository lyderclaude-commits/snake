#!/usr/bin/env bash
# Construit Wakabi-2.0-maquette.apk sans SDK Android (outils récupérés sur Maven Central).
set -euo pipefail
HERE="$(cd "$(dirname "$0")" && pwd)"; MOCK="$(dirname "$HERE")"
TOOLS="${TOOLS:-$HOME/.cache/wakabi-apk-tools}"; mkdir -p "$TOOLS"
R=https://repo.maven.apache.org/maven2
get(){ [ -f "$TOOLS/$2" ] || curl -sfL "$R/$1/$2" -o "$TOOLS/$2"; }
get com/google/android/android/4.1.1.4 android-4.1.1.4.jar
get com/jakewharton/android/repackaged/dalvik-dx/16.0.1 dalvik-dx-16.0.1.jar
get com/android/tools/build/apksig/2.3.0 apksig-2.3.0.jar
if [ ! -d "$TOOLS/poppins" ]; then (cd "$TOOLS" && npm pack @fontsource/poppins@5.3.0 --silent >/dev/null && mkdir poppins && tar xzf fontsource-poppins-5.3.0.tgz -C poppins); fi

B="$HERE/build"; rm -rf "$B"; mkdir -p "$B"/{stubs,classes,www/fonts,signer}
# 1. Java -> .class -> classes.dex
javac --release 8 -nowarn -d "$B/stubs" "$HERE"/stubs/android/webkit/*.java
javac --release 8 -nowarn -cp "$TOOLS/android-4.1.1.4.jar:$B/stubs" -d "$B/classes" "$HERE"/src/com/wakabi/mockup/*.java
java -cp "$TOOLS/dalvik-dx-16.0.1.jar" com.android.dx.command.Main --dex --min-sdk-version=24 --output="$B/classes.dex" "$B/classes"
# 2. Contenu web (maquette + mode application + polices locales)
cp -r "$MOCK/img" "$MOCK/base.css" "$MOCK/app.css" "$HERE"/web/mobile.css "$HERE"/web/mobile.js "$HERE"/web/fonts.css "$B/www/"
cp "$TOOLS"/poppins/package/files/poppins-latin-{400,500,600,700,800}-normal.woff2 "$B/www/fonts/"
sed -e 's#<link href="https://fonts.googleapis.com[^>]*>#<link rel="stylesheet" href="fonts.css">#' \
    -e 's#</head>#<link rel="stylesheet" href="mobile.css">\n</head>#' \
    -e 's#</body>#<script src="mobile.js"></script>\n</body>#' "$MOCK/index.html" > "$B/www/index.html"
# 3. APK non signé, puis signature v2
python3 "$HERE/tools/build_apk.py" "$B/classes.dex" "$B/www" "$HERE/icon.png" "$B/unsigned.apk"
KS="$HERE/wakabi-debug.p12"
[ -f "$KS" ] || keytool -genkeypair -keystore "$KS" -storetype PKCS12 -storepass wakabi123 -keypass wakabi123 -alias wakabi \
  -keyalg RSA -keysize 2048 -validity 10000 -dname "CN=Wakabi Maquette, O=Wakabi, C=TG" 2>/dev/null
javac -nowarn -cp "$TOOLS/apksig-2.3.0.jar" -d "$B/signer" "$HERE/tools/Sign.java"
java --add-exports java.base/sun.security.x509=ALL-UNNAMED --add-exports java.base/sun.security.pkcs=ALL-UNNAMED --add-exports java.base/sun.security.util=ALL-UNNAMED -cp "$TOOLS/apksig-2.3.0.jar:$B/signer" Sign "$B/unsigned.apk" "$MOCK/Wakabi-2.0-maquette.apk" "$KS" wakabi123 wakabi
ls -la "$MOCK/Wakabi-2.0-maquette.apk"
