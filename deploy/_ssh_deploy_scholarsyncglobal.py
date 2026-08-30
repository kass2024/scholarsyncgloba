#!/usr/bin/env python3
"""SFTP deployment for the isolated ScholarSync Global domain."""
from __future__ import annotations

import os
import posixpath
import shlex
import stat
from pathlib import Path

import paramiko


ROOT = Path(__file__).resolve().parents[1]
REMOTE_ROOT = os.environ.get("DEPLOY_REMOTE_ROOT", "/home/visawgnz/scholarsyncglobal.ca")
SKIP_DIRS = {
    ".git",
    ".cursor",
    "node_modules",
    "vendor",
    "uploads",
    "files",
    "documents",
    "contracts",
    "signatures",
    "logs",
    "admission_letters",
    "storage",
    "__pycache__",
}
SKIP_FILES = {
    ".env",
    "credentials.json",
}


def required(name: str) -> str:
    value = os.environ.get(name, "").strip()
    if not value:
        raise RuntimeError(f"{name} is required")
    return value


def ensure_dir(sftp: paramiko.SFTPClient, path: str) -> None:
    parts = path.strip("/").split("/")
    current = ""
    for part in parts:
        current += "/" + part
        try:
            sftp.stat(current)
        except FileNotFoundError:
            sftp.mkdir(current)


def upload_tree(sftp: paramiko.SFTPClient) -> int:
    uploaded = 0
    for directory, dirnames, filenames in os.walk(ROOT):
        dirnames[:] = [name for name in dirnames if name not in SKIP_DIRS]
        local_dir = Path(directory)
        remote_dir = posixpath.join(REMOTE_ROOT, local_dir.relative_to(ROOT).as_posix())
        ensure_dir(sftp, remote_dir)
        for filename in filenames:
            local_path = local_dir / filename
            relative = local_path.relative_to(ROOT)
            if (
                filename in SKIP_FILES
                or filename.endswith(".log")
                or filename.endswith((".docx", ".xlsx"))
                or (filename.endswith(".pdf") and "PHPMailer" not in relative.parts)
            ):
                continue
            remote_path = posixpath.join(remote_dir, filename)
            sftp.put(str(local_path), remote_path)
            sftp.chmod(remote_path, stat.S_IRUSR | stat.S_IWUSR | stat.S_IRGRP | stat.S_IROTH)
            uploaded += 1
    return uploaded


def write_remote_env(sftp: paramiko.SFTPClient) -> None:
    values = {
        "DB_HOST": os.environ.get("DB_HOST", "127.0.0.1"),
        "DB_NAME": os.environ.get("DB_NAME", "visawgnz_scholarsyncglobal"),
        "DB_USER": required("DB_USER"),
        "DB_PASS": required("DB_PASS"),
        "SMTP_HOST": os.environ.get("SMTP_HOST", "scholarsyncglobal.ca"),
        "SMTP_USERNAME": "infos@scholarsyncglobal.ca",
        "SMTP_PASSWORD": required("SMTP_PASSWORD"),
        "SMTP_PORT": os.environ.get("SMTP_PORT", "465"),
        "SMTP_FROM_EMAIL": "infos@scholarsyncglobal.ca",
        "SMTP_FROM_NAME": "ScholarSync Global",
        "APP_PUBLIC_URL": "https://scholarsyncglobal.ca",
    }
    payload = "".join(f"{key}={value}\n" for key, value in values.items())
    remote_path = posixpath.join(REMOTE_ROOT, ".env")
    with sftp.file(remote_path, "w") as handle:
        handle.write(payload)
    sftp.chmod(remote_path, stat.S_IRUSR | stat.S_IWUSR)


def main() -> None:
    host = required("DEPLOY_HOST")
    port = int(os.environ.get("DEPLOY_PORT", "21098"))
    user = required("DEPLOY_USER")
    password = required("DEPLOY_PASSWORD")

    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    client.connect(host, port=port, username=user, password=password, timeout=30,
                   look_for_keys=False, allow_agent=False)
    try:
        sftp = client.open_sftp()
        try:
            ensure_dir(sftp, REMOTE_ROOT)
            uploaded = upload_tree(sftp)
            write_remote_env(sftp)
        finally:
            sftp.close()
        command = f"cd {shlex.quote(REMOTE_ROOT)} && composer install --no-dev --no-interaction --optimize-autoloader"
        _, stdout, stderr = client.exec_command(command, timeout=600)
        exit_code = stdout.channel.recv_exit_status()
        if exit_code != 0:
            message = stderr.read().decode("utf-8", "replace").strip()
            raise RuntimeError(f"composer install failed: {message}")
        print(f"Uploaded {uploaded} files to {REMOTE_ROOT}")
    finally:
        client.close()


if __name__ == "__main__":
    main()
