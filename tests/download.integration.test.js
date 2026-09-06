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
const distr = path.join(root, "distr");
const media = path.join(root, "media");
const data = path.join(root, "bsl-data");
const log = path.join(data, "download.log");
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
    for (const directory of [site, distr, media, data]) {
        fs.mkdirSync(directory, { recursive: true });
    }
    fs.copyFileSync(path.join(repo, "download.php"), path.join(site, "download.php"));
    const distributionsRegistry = path.join(data, "distributions-registry.json");
    const mediaRegistry = path.join(data, "media-registry.json");
    fs.copyFileSync(path.join(repo, "config", "distributions-registry.json"), distributionsRegistry);
    fs.copyFileSync(path.join(repo, "config", "media-registry.seed.json"), mediaRegistry);
    const originalMediaRegistry = fs.readFileSync(mediaRegistry, "utf8");
    fs.writeFileSync(path.join(media, "manifest.mp3"), "test-audio");
    fs.writeFileSync(path.join(distr, "plg_content_bslmediaembed-0.1.1.zip"), "test-zip");

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

    for (const key of ["unknown", "../media/manifest.mp3"]) {
        response = await fetch(`${url}?file=${encodeURIComponent(key)}`);
        assert.equal(response.status, 400);
        assert.equal(await response.text(), "Error: invalid key");
    }

    response = await fetch(`${url}?file=bsl-tagcloud-1.2.0`);
    assert.equal(response.status, 404);
    assert.equal(await response.text(), "Error: file not found");

    response = await fetch(`${url}?file=manifest-audio`, { method: "HEAD" });
    assert.equal(response.status, 200);
    assert.equal(response.headers.get("content-type"), "audio/mpeg");
    assert.equal((await response.arrayBuffer()).byteLength, 0);
    assert.deepEqual(logLines(), []);

    response = await fetch(`${url}?file=manifest-audio&source=site`);
    assert.equal(response.status, 200);
    assert.equal(response.headers.get("content-type"), "audio/mpeg");
    assert.equal(Buffer.from(await response.arrayBuffer()).toString(), "test-audio");
    assert.match(logLines().at(-1), /\tmanifest-audio\tsite$/);

    response = await fetch(`${url}?file=manifest-audio&source=forged`);
    assert.equal(response.status, 200);
    await response.arrayBuffer();
    assert.match(logLines().at(-1), /\tmanifest-audio\tdirect$/);

    response = await fetch(`${url}?file=bsl-media-embed-0.1.1&download=1&source=joomla`);
    assert.equal(response.status, 200);
    assert.equal(response.headers.get("content-type"), "application/zip");
    assert.equal(response.headers.get("content-disposition"), 'attachment; filename="plg_content_bslmediaembed-0.1.1.zip"');
    assert.equal(Buffer.from(await response.arrayBuffer()).toString(), "test-zip");
    assert.match(logLines().at(-1), /\tbsl-media-embed-0\.1\.1\tjoomla$/);

    const logCountBeforeConfigurationErrors = logLines().length;

    fs.writeFileSync(mediaRegistry, "{", "utf8");
    response = await fetch(`${url}?file=manifest-audio`);
    assert.equal(response.status, 500);
    assert.equal(await response.text(), "Error: service configuration unavailable");
    fs.writeFileSync(mediaRegistry, originalMediaRegistry, "utf8");

    const missingMediaRegistry = `${mediaRegistry}.missing`;
    fs.renameSync(mediaRegistry, missingMediaRegistry);
    try {
        response = await fetch(`${url}?file=manifest-audio`);
        assert.equal(response.status, 500);
        assert.equal(await response.text(), "Error: service configuration unavailable");
    } finally {
        fs.renameSync(missingMediaRegistry, mediaRegistry);
    }

    const invalidMediaRegistry = JSON.parse(originalMediaRegistry);
    invalidMediaRegistry["manifest-audio"] = "../media/manifest.mp3";
    fs.writeFileSync(mediaRegistry, JSON.stringify(invalidMediaRegistry), "utf8");
    response = await fetch(`${url}?file=manifest-audio`);
    assert.equal(response.status, 500);
    assert.equal(await response.text(), "Error: service configuration unavailable");
    fs.writeFileSync(mediaRegistry, originalMediaRegistry, "utf8");

    const duplicateMediaRegistry = JSON.parse(originalMediaRegistry);
    duplicateMediaRegistry["bsl-media-embed-0.1.1"] = "manifest.mp3";
    fs.writeFileSync(mediaRegistry, JSON.stringify(duplicateMediaRegistry), "utf8");
    response = await fetch(`${url}?file=manifest-audio`);
    assert.equal(response.status, 500);
    assert.equal(await response.text(), "Error: service configuration unavailable");
    fs.writeFileSync(mediaRegistry, originalMediaRegistry, "utf8");

    assert.equal(logLines().length, logCountBeforeConfigurationErrors);

    console.log("OK: download gateway integration tests passed");
}

run().catch((error) => {
    console.error(error);
    process.exitCode = 1;
}).finally(() => {
    if (server && server.exitCode === null) server.kill();
    setTimeout(() => fs.rmSync(root, { recursive: true, force: true }), 500);
});
