"nano /etc/systemd/system/monero-deposit-daemon.service"

paste this

 GNU nano 7.2          /etc/systemd/system/monero-deposit-daemon.service
"
[Unit]
Description=Monero Deposit Scanner Daemon
After=network.target monero-wallet-rpc.service
Requires=monero-wallet-rpc.service
[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/var/www/moneromarket/daemon
ExecStart=/usr/bin/php deposit_daemon.php
Restart=always
RestartSec=5
# Hardening
NoNewPrivileges=true
PrivateTmp=true
ProtectSystem=full
ProtectHome=true
ReadWritePaths=/var/www/moneromarket
# Logging
StandardOutput=journal
StandardError=journal
[Install]
WantedBy=multi-user.target

"
