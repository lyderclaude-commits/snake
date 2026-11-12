#!/usr/bin/env python3
"""Assemble un APK non signé sans le SDK Android :
- AndroidManifest.xml au format XML binaire (AXML)
- resources.arsc minimal (une seule ressource : l'icône)
- classes.dex + assets/www/*
Usage: build_apk.py <classes.dex> <www_dir> <icon.png> <out.apk>
"""
import os, struct, sys, zipfile

PKG = "com.wakabi.mockup"
ANDROID_NS = "http://schemas.android.com/apk/res/android"
ATTR = {  # android.R.attr
    "theme": 0x01010000, "label": 0x01010001, "icon": 0x01010002, "name": 0x01010003,
    "exported": 0x01010010, "screenOrientation": 0x0101001e, "configChanges": 0x0101001f,
    "minSdkVersion": 0x0101020c, "versionCode": 0x0101021b, "versionName": 0x0101021c,
    "targetSdkVersion": 0x01010270, "hardwareAccelerated": 0x010102d3,
}
T_REF, T_STR, T_DEC, T_HEX, T_BOOL = 0x01, 0x03, 0x10, 0x11, 0x12
ICON_ID = 0x7F010000
THEME_FULLSCREEN = 0x0103000A  # @android:style/Theme.Black.NoTitleBar.Fullscreen


def pad4(b):
    return b + b"\0" * (-len(b) % 4)


def string_pool(strings, utf8=False):
    offs, data = [], b""
    for s in strings:
        offs.append(len(data))
        if utf8:
            e = s.encode("utf-8")
            assert len(s) < 128 and len(e) < 128
            data += bytes([len(s), len(e)]) + e + b"\0"
        else:
            data += struct.pack("<H", len(s)) + s.encode("utf-16-le") + b"\0\0"
    data = pad4(data)
    hdr = 28
    start = hdr + 4 * len(strings)
    body = b"".join(struct.pack("<I", o) for o in offs) + data
    flags = 0x100 if utf8 else 0
    return struct.pack("<HHIIIIII", 0x0001, hdr, hdr + len(body), len(strings), 0, flags, start, 0) + body


# ---------------------------------------------------------------- manifest
def manifest(target_sdk=34, min_sdk=24):
    act = PKG + ".MainActivity"
    tree = ("manifest", [("", "package", T_STR, PKG), ("a", "versionCode", T_DEC, 1), ("a", "versionName", T_STR, "2.0")], [
        ("uses-sdk", [("a", "minSdkVersion", T_DEC, min_sdk), ("a", "targetSdkVersion", T_DEC, target_sdk)], []),
        ("application", [("a", "theme", T_REF, THEME_FULLSCREEN), ("a", "label", T_STR, "Wakabi 2.0"),
                         ("a", "icon", T_REF, ICON_ID), ("a", "hardwareAccelerated", T_BOOL, True)], [
            ("activity", [("a", "name", T_STR, act), ("a", "exported", T_BOOL, True),
                          ("a", "screenOrientation", T_DEC, 1), ("a", "configChanges", T_HEX, 0x04A0)], [
                ("intent-filter", [], [
                    ("action", [("a", "name", T_STR, "android.intent.action.MAIN")], []),
                    ("category", [("a", "name", T_STR, "android.intent.category.LAUNCHER")], []),
                ]),
            ]),
        ]),
    ])
    # string pool : d'abord les noms d'attributs android (alignés sur la resource map)
    used = []

    def walk(n):
        for ns, name, *_ in n[1]:
            if ns == "a" and name not in used:
                used.append(name)
        for c in n[2]:
            walk(c)
    walk(tree)
    used.sort(key=lambda k: ATTR[k])
    strings = list(used)

    def sid(s):
        if s not in strings:
            strings.append(s)
        return strings.index(s)
    for s in ("android", ANDROID_NS):
        sid(s)

    body = []
    NO = 0xFFFFFFFF

    def node(n):
        tag, attrs, kids = n
        out = []
        recs = []
        for ns, name, t, v in attrs:
            nsi = sid(ANDROID_NS) if ns == "a" else NO
            ni = sid(name)
            raw = NO
            if t == T_STR:
                raw = sid(v); data = raw
            elif t == T_BOOL:
                data = 0xFFFFFFFF if v else 0
            else:
                data = v
            recs.append((ATTR.get(name, 0) if ns == "a" else 0, struct.pack("<IIIHBBI", nsi, ni, raw, 8, 0, t, data)))
        recs.sort(key=lambda r: r[0])
        ext = struct.pack("<IIHHHHHH", NO, sid(tag), 20, 20, len(recs), 0, 0, 0)
        payload = ext + b"".join(r[1] for r in recs)
        out.append(struct.pack("<HHIII", 0x0102, 16, 16 + len(payload), 1, NO) + payload)
        for k in kids:
            out += node(k)
        out.append(struct.pack("<HHIIIII", 0x0103, 16, 24, 1, NO, NO, sid(tag)))
        return out

    elems = node(tree)
    ns_start = struct.pack("<HHIIIII", 0x0100, 16, 24, 1, NO, sid("android"), sid(ANDROID_NS))
    ns_end = struct.pack("<HHIIIII", 0x0101, 16, 24, 1, NO, sid("android"), sid(ANDROID_NS))
    pool = string_pool(strings)
    resmap = struct.pack("<HHI", 0x0180, 8, 8 + 4 * len(used)) + b"".join(struct.pack("<I", ATTR[k]) for k in used)
    content = pool + resmap + ns_start + b"".join(elems) + ns_end
    return struct.pack("<HHI", 0x0003, 8, 8 + len(content)) + content


# ---------------------------------------------------------------- resources.arsc
ICON_PATH = "res/drawable-nodpi-v4/icon.png"


def resources():
    gpool = string_pool([ICON_PATH], utf8=True)
    tpool = string_pool(["drawable"], utf8=True)
    kpool = string_pool(["icon"], utf8=True)
    spec = struct.pack("<HHIBBHI", 0x0202, 16, 20, 1, 0, 0, 1) + struct.pack("<I", 0)
    config = bytearray(64)
    struct.pack_into("<I", config, 0, 64)
    struct.pack_into("<H", config, 14, 0xFFFF)  # density = nodpi
    struct.pack_into("<H", config, 24, 4)  # v4
    hdr_size = 8 + 4 + 4 + 4 + 64
    entries = struct.pack("<HHI", 8, 0, 0) + struct.pack("<HBBI", 8, 0, T_STR, 0)
    offsets = struct.pack("<I", 0)
    tchunk_body = struct.pack("<BBHII", 1, 0, 0, 1, hdr_size + 4) + bytes(config) + offsets + entries
    tchunk = struct.pack("<HHI", 0x0201, hdr_size, 8 + len(tchunk_body)) + tchunk_body
    name = PKG.encode("utf-16-le").ljust(256, b"\0")
    pkg_hdr = 288
    type_off = pkg_hdr
    key_off = pkg_hdr + len(tpool)
    pkg_body = tpool + kpool + spec + tchunk
    pkg = struct.pack("<HHII", 0x0200, pkg_hdr, pkg_hdr + len(pkg_body), 0x7F) + name + \
        struct.pack("<IIIII", type_off, 1, key_off, 1, 0) + pkg_body
    content = gpool + pkg
    return struct.pack("<HHII", 0x0002, 12, 12 + len(content), 1) + content


# ---------------------------------------------------------------- zip
def main(dex, www, icon, out):
    with zipfile.ZipFile(out, "w") as z:
        z.writestr(zipfile.ZipInfo("AndroidManifest.xml", (2026, 1, 1, 0, 0, 0)), manifest(), zipfile.ZIP_DEFLATED)
        zi = zipfile.ZipInfo("resources.arsc", (2026, 1, 1, 0, 0, 0)); zi.compress_type = zipfile.ZIP_STORED
        z.writestr(zi, resources())
        z.write(dex, "classes.dex", zipfile.ZIP_DEFLATED)
        z.write(icon, ICON_PATH, zipfile.ZIP_STORED)
        for root, _, files in os.walk(www):
            for f in sorted(files):
                p = os.path.join(root, f)
                arc = "assets/www/" + os.path.relpath(p, www).replace(os.sep, "/")
                comp = zipfile.ZIP_STORED if f.endswith((".jpg", ".png", ".woff2")) else zipfile.ZIP_DEFLATED
                z.write(p, arc, comp)
    print("unsigned apk:", out, os.path.getsize(out), "bytes")


if __name__ == "__main__":
    main(*sys.argv[1:5])
