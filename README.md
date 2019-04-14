# UltraNote-WP-PayemtGateway
UltraNote WordPress-WooCommerce Payment Gateway.

Most likely you will need to run walletd and generating a new container on local or remote host and connect the plugin to walletd API.

##### Available configuration options:
```ini
container-file=	            # REQUIRED. Path to wallet container
container-password= 	    # REQUIRED. Password
data-dir=/srv/ultranote	    # Where blockchain data is stored. Defaults to /home/user/.UltraNote
bind-address=0.0.0.0	    # Use server IP or 127.0.0.1
bind-port=8070		    # Port
rpc-user=                   # Auth user for RPC service
rpc-password=               # Password for RPC service
server-root=/srv/ultranote  # Working directory
log-level=                  # Log level 0-4
local=1                     # Use In-process node
daemon-address=localhost    # remote daemon address, don't use it with in-process mode
daemon-port=30000           # 
```

##### Minimal Configuration
Here is minimal cofiguration example required to run the daemon:
`/etc/paymentgate.conf`
```ini
container-file=/etc/ultranote/container.iwallet
bind-port=8070
rpc-user=username
rpc-password=password
bind-address=127.0.0.1
container-password=secret123
log-file=/var/log/walletd.log
local=1
```

#### 3. Generate wallet container:
```sh
~$ walletd --config=/etc/paymentgate.conf --generate-container
```
