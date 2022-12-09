# Oathkeeper, Kratos and Keto example
## Authentification, ACL and 2FA

This repository show how create an SSO and ACL system with the Ory stack and Kubernetes.
It use the [kratos-selfservice-ui-node](https://github.com/ory/kratos-selfservice-ui-node), a fork of [mailslurper](https://github.com/pngouin/mailslurper) and a [php app](https://github.com/pngouin/react-admin-ory) for the *admin* page (I'm not an front-end dev the admin page work, nothing plus).


## How to run

You need to install the following tools:
| URL | Description |
| :--- | :--- |
| **[minikube](https://minikube.sigs.k8s.io/docs/start/)**|minikube is local Kubernetes, focusing on making it easy to learn and develop for Kubernetes. |
| **[kustomize](https://kubectl.docs.kubernetes.io/installation/kustomize/)**|Kubernetes native configuration management|
| **[kubectl](https://kubernetes.io/docs/tasks/tools/install-kubectl/)**| The Kubernetes command-line tool|
| **[tiltdev](https://docs.tilt.dev/install.html)**|A toolkit for fixing the pains of microservice development|
Optional suggestions
| **[dbeaver](https://dbeaver.io/)**|Free multi-platform database tool for developers, SQL programmers, database administrators and analysts.|
| **[openlens](https://github.com/lensapp/lens)**|Lens is the free and open-source Kubernetes IDE|

```bash
$ minikube start
$ minikube addons enable ingress
$ minikube tunnel

# Create all the resources
$ tilt up

# Check that ingress are created with minikube tunnel
$ kubectl get ingress
NAME               CLASS    HOSTS            ADDRESS        PORTS   AGE
fake-smtp-server   <none>   mail.test.info   192.168.XXX.XXX   80      119s
oathkeeper         <none>   ory.test.info    192.168.XXX.XXX   80      119s

# Add ingress domains to the local hosts file
$ sudo bash -c 'cat << EOF >> /etc/hosts
# ORY Minikube SSO stack
127.0.0.1   mail.test.info
127.0.0.1   ory.test.info
EOF'

# Open your browser and open http://ory.test.info/panel/welcome and http://mail.test.info
```

## How to use

Go to http://ory.test.info/panel/ and create an account, you can validate your mail on http://mail.test.info. When you create an account you have to role `user` or `admin`. Only the admin role have the right to access the admin react app.

| URL | Description |
| :--- | :--- |
| http://ory.test.info/panel/welcome | User app for create an account, login, other |
| http://ory.test.info/admin/ | Admin react app, you need the role `admin` to access |
| http://ory.test.info/elrapp | PHP application routed through oathkeeper proxy |
| http://mail.test.info | Local mail panel, you will receive mail confirmation here |

## How it works

![schema](.docs/diagram.png)

*(This is an outline and does not exactly reflect the reality of how the stack works)*
