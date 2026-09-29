"use strict";

const assert = require("node:assert/strict");
const { spawn } = require("node:child_process");
const fs = require("node:fs");
const os = require("node:os");
const path = require("node:path");

const php = process.argv[2];
if (!php) throw new Error("Pass the path to php.exe as the first argument");

const repo = path.resolve(__dirname, "..");
const root = fs.mkdtempSync(path.join(os.tmpdir(), "bsl-download-service-"));
const site = path.join(root, "site");
const log = path.join(root, "bsl-data", "download.log");
const port = 18000 + process.pid % 10000;
const url = `http://127.0.0.1:${port}/download.php`;
let server;
let serverOutput = "";

function logLines() {
    return fs.existsSync(log)
        ? fs.readFileSync(log, "utf8").trim().split(/\r?\n/).filter(Boolean)
        : [];
}

async function waitForServer() {
    for (let attempt = 0; attempt < 100; attempt += 1) {
        try {
            await fetch(`${url}?file=health-check`);
            return;
        } catch {
            await new Promise((resolve) => setTimeout(resolve, 50));
        }
    }
    throw new Error(`PHP server did not start:\n${serverOutput}`);
}

async function run() {
    fs.mkdirSync(site, { recursive: true });
    fs.mkdirSync(path.dirname(log), { recursive: true });
    fs.copyFileSync(path.join(repo, "download.php"), path.join(site, "download.php"));

    const fixtures = new Map([
        ["media/manifest.mp3", "test-audio"],
        ["distr/7z2601-x64.exe", "test-7zip"],
        ["distr/bsl-tor_0.4.7_x64-setup.zip", "test-bsl-tor"],
        ["distr/tor-browser-windows-x86_64-portable-15.0.11.exe", "test-tor-portable"],
        ["distr/tor-expert-bundle-windows-x86_64-15.0.11.tar.gz", "test-tor-bundle"],
        ["distr/bsl-media-embed/plg_content_bslmediaembed-0.1.1.zip", "test-media-011"],
        ["distr/bsl-media-embed/plg_content_bslmediaembed-0.2.0.zip", "test-media-020"],
        ["distr/bsl-timer/bsl-timer_0.7.2_x64-setup.exe", "test-timer"],
    ]);

    for (const [relativePath, contents] of fixtures) {
        const fullPath = path.join(root, relativePath);
        fs.mkdirSync(path.dirname(fullPath), { recursive: true });
        fs.writeFileSync(fullPath, contents);
    }

    server = spawn(php, ["-n", "-S", `127.0.0.1:${port}`, "-t", site], {
        windowsHide: true,
        stdio: ["ignore", "pipe", "pipe"],
    });
    server.stdout.on("data", (chunk) => serverOutput += chunk);
    server.stderr.on("data", (chunk) => serverOutput += chunk);
    await waitForServer();

    let response = await fetch(`${url}?file=manifest-audio`, { method: "POST" });
    assert.equal(response.status, 405);
    assert.equal(response.headers.get("allow"), "GET, HEAD");

    for (const query of [
        "file=unknown",
        `file=${encodeURIComponent("../media/manifest.mp3")}`,
        "product=unknown&file=artifact.zip",
        `product=bsl-timer&file=${encodeURIComponent("../manifest.mp3")}`,
        "product=bsl-timer&file=missing.exe",
        "product=bsl-timer&file=notes.txt",
    ]) {
        response = await fetch(`${url}?${query}`);
        assert.equal(response.status, 404, query);
        assert.equal(await response.text(), "404 Not Found", query);
    }
    assert.deepEqual(logLines(), []);

    response = await fetch(`${url}?file=manifest-audio`, { method: "HEAD" });
    assert.equal(response.status, 200);
    assert.equal(response.headers.get("content-type"), "audio/mpeg");
    assert.equal((await response.arrayBuffer()).byteLength, 0);
    assert.deepEqual(logLines(), []);

    response = await fetch(`${url}?file=manifest-audio&source=site`);
    assert.equal(response.status, 200);
    assert.equal(response.headers.get("content-type"), "audio/mpeg");
    assert.equal(await response.text(), "test-audio");
    assert.match(logLines().at(-1), /\tmanifest-audio\tsite$/);

    response = await fetch(`${url}?file=manifest-audio&source=forged`);
    assert.equal(response.status, 200);
    await response.arrayBuffer();
    assert.match(logLines().at(-1), /\tmanifest-audio\tdirect$/);

    response = await fetch(`${url}?product=bsl-timer&file=bsl-timer_0.7.2_x64-setup.exe&source=updater&download=1`);
    assert.equal(response.status, 200);
    assert.equal(response.headers.get("content-disposition"), 'attachment; filename="bsl-timer_0.7.2_x64-setup.exe"');
    assert.equal(await response.text(), "test-timer");
    assert.match(logLines().at(-1), /\tbsl-timer\/bsl-timer_0\.7\.2_x64-setup\.exe\tupdater$/);

    for (const [key, body] of [
        ["tor", "test-tor-portable"],
        ["bsl-tor", "test-bsl-tor"],
        ["tor-bundle", "test-tor-bundle"],
        ["7zip", "test-7zip"],
        ["bsl-media-embed-0.1.1", "test-media-011"],
        ["bsl-media-embed-0.2.0", "test-media-020"],
    ]) {
        response = await fetch(`${url}?file=${key}&source=joomla`);
        assert.equal(response.status, 200, key);
        assert.equal(await response.text(), body, key);
        assert.equal(logLines().at(-1).split("\t")[1], key);
    }

    console.log("OK: download gateway integration tests passed");
}

run().catch((error) => {
    console.error(error);
    process.exitCode = 1;
}).finally(() => {
    if (server && server.exitCode === null) server.kill();
    setTimeout(() => fs.rmSync(root, { recursive: true, force: true }), 500);
});
