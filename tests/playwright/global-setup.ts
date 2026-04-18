import { spawnSync } from 'child_process';

export default async function globalSetup(): Promise<void> {
  const result = spawnSync('php', ['bin/seed-test-user'], {
    cwd: process.cwd(),
    stdio: 'inherit',
  });
  if (result.status !== 0) {
    throw new Error(`seed-test-user exited with status ${result.status}`);
  }
}
