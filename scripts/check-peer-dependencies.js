import fs from 'node:fs';
import path from 'node:path';
import semver from 'semver';

function validatePeerDependencies() {
    const cwd = process.cwd();
    const nodeModulesPath = path.join(cwd, 'node_modules');
    const rootPackagePath = path.join(cwd, 'package.json');

    if (!fs.existsSync(rootPackagePath)) {
        console.error('Error: package.json not found.');
        process.exit(1);
    }

    if (!fs.existsSync(nodeModulesPath)) {
        console.error('Error: node_modules directory not found. Please install dependencies first.');
        process.exit(1);
    }

    const rootPackage = JSON.parse(fs.readFileSync(rootPackagePath, 'utf8'));
    const isVitePlusAliased = Boolean(
        rootPackage.devDependencies?.vite?.startsWith('npm:@voidzero-dev/vite-plus-core') ||
        rootPackage.dependencies?.vite?.startsWith('npm:@voidzero-dev/vite-plus-core'),
    );

    const installed = new Map();

    function scanDirectory(dir) {
        const entries = fs.readdirSync(dir, { withFileTypes: true });
        for (const entry of entries) {
            if (entry.name.startsWith('.')) {
                continue;
            }

            const fullPath = path.join(dir, entry.name);
            if (entry.isDirectory()) {
                if (entry.name.startsWith('@')) {
                    scanDirectory(fullPath);
                } else {
                    const packageJsonPath = path.join(fullPath, 'package.json');
                    if (fs.existsSync(packageJsonPath)) {
                        try {
                            const data = JSON.parse(fs.readFileSync(packageJsonPath, 'utf8'));
                            if (data.name && data.version) {
                                installed.set(data.name, {
                                    version: data.version,
                                    dir: fullPath,
                                    manifest: data,
                                });
                            }
                        } catch {
                            // Ignore unparseable manifests
                        }
                    }
                }
            }
        }
    }

    scanDirectory(nodeModulesPath);

    const errors = [];

    for (const [name, info] of installed.entries()) {
        const peerDependencies = info.manifest.peerDependencies;
        if (!peerDependencies || typeof peerDependencies !== 'object') {
            continue;
        }

        const peerMeta = info.manifest.peerDependenciesMeta || {};

        for (const [peerName, peerRange] of Object.entries(peerDependencies)) {
            const isOptional = peerMeta[peerName]?.optional === true;

            // Vite+ core alias provides full Vite compatibility
            if (peerName === 'vite' && isVitePlusAliased) {
                continue;
            }

            const installedPeer = installed.get(peerName);

            if (!installedPeer) {
                if (!isOptional) {
                    errors.push(`Missing peer dependency: "${name}" requires "${peerName}@${peerRange}", but "${peerName}" is not installed.`);
                }
                continue;
            }

            if (semver.validRange(peerRange)) {
                if (!semver.satisfies(installedPeer.version, peerRange, { includePrerelease: true })) {
                    errors.push(
                        `Incompatible peer dependency: "${name}" requires "${peerName}@${peerRange}", but found installed version "${peerName}@${installedPeer.version}".`,
                    );
                }
            }
        }
    }

    if (errors.length > 0) {
        console.error('\nPeer dependency compatibility check failed:');
        for (const err of errors) {
            console.error(`  ✖ ${err}`);
        }
        process.exit(1);
    }

    console.log('✔ All frontend peer dependencies are strictly satisfied.');
}

validatePeerDependencies();
