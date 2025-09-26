# 🎮 Steam Server Status SourceQuery PHP - SSSP

<div align="center">

![WordPress](https://img.shields.io/badge/WordPress-5.0+-21759B?style=for-the-badge&logo=wordpress&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-7.4+-777BB4?style=for-the-badge&logo=php&logoColor=white)

**Un plugin WordPress élégant pour afficher le statut en temps réel de vos serveurs Steam**

[Installation](#-installation) • [Utilisation](#-utilisation) • [Fonctionnalités](#-fonctionnalités) • [Contribuer](#-contribuer)

</div>

---

## 📖 Description

**Steam Server Status** est un plugin WordPress moderne qui permet d'afficher facilement le nombre de joueurs connectés sur un ou plusieurs serveurs Steam compatibles SourceQuery. Parfait pour les communautés de joueurs qui souhaitent partager l'activité de leurs serveurs sur leur site web.

## ✨ Fonctionnalités

- 🟢 **Statut en temps réel** - Affichage du statut serveur (en ligne/hors ligne)
- 👥 **Compteur de joueurs** - Nombre de joueurs connectés et capacité maximale
- 🔧 **Multi-serveurs** - Support de plusieurs serveurs configurables
- ⚡ **Cache intégré** - Système de cache (15s par défaut) pour optimiser les performances
- 🎨 **Compatible Elementor** - Personnalisation avancée via CSS
- 📱 **Responsive** - Interface adaptée à tous les écrans
- 🚀 **Shortcode simple** - Intégration facile dans vos pages et articles

## 🛠️ Installation

### 1️⃣ Installation via Git

```bash
cd wp-content/plugins/
git clone https://github.com/skylidefr/steam-server-status.git steam-server-status
```

### 2️⃣ Configuration

1. **Activez le plugin** via le menu **Extensions** dans WordPress
2. Allez dans **Réglages → Steam Status** 
3. **Ajoutez vos serveurs** avec l'adresse IP et le port
4. **Testez la connexion** pour vérifier la configuration

### 3️⃣ Utilisation

Utilisez le shortcode suivant pour afficher le statut :

```php
[steam_status id="0" show_name="1"]
```

## 🎯 Utilisation

### Shortcode

| Paramètre | Description | Valeurs | Défaut |
|-----------|-------------|---------|---------|
| `id` | Identifiant du serveur | `0`, `1`, `2`... | `0` |
| `show_name` | Afficher le nom du serveur | `1` (oui), `0` (non) | `1` |

### 💡 Exemples

```php
// Afficher le premier serveur avec son nom
[steam_status id="0" show_name="1"]

// Afficher le deuxième serveur sans nom
[steam_status id="1" show_name="0"]

// Afficher tous les serveurs
[steam_status id="all"]
```

### 🎨 Personnalisation CSS

```css
.steam-server-status {
    background: linear-gradient(135deg, #171a21, #2a475e);
    border-radius: 10px;
    padding: 20px;
    color: white;
}

.server-online {
    border-left: 4px solid #66c0f4;
}

.server-offline {
    border-left: 4px solid #e74c3c;
}
```

## 📋 Prérequis

- **WordPress** 5.0 ou supérieur
- **PHP** 7.4 ou supérieur  
- **Extension PHP Socket** (pour les requêtes SourceQuery)
- **Serveur Steam** compatible SourceQuery

## 🎮 Jeux compatibles

Ce plugin fonctionne avec tous les jeux Steam utilisant le protocole SourceQuery :

- Counter-Strike: Global Offensive
- Counter-Strike 2
- Team Fortress 2
- Garry's Mod
- Left 4 Dead 2
- Rust
- ARK: Survival Evolved
- Valheim
- Et bien d'autres...

## 📸 Captures d'écran

<details>
<summary>🖼️ Voir les captures d'écran</summary>

### Interface d'administration
![Admin Panel](screenshots/admin-panel.png)

### Affichage front-end
![Frontend Display](screenshots/frontend.png)

### Widget Elementor
![Elementor Widget](screenshots/elementor.png)

</details>

## 🤝 Contribuer

Les contributions sont les bienvenues ! 

1. **Forkez** le projet
2. Créez votre branche : `git checkout -b feature/amazing-feature`
3. **Commitez** vos changements : `git commit -m 'Add amazing feature'`
4. **Pushez** sur la branche : `git push origin feature/amazing-feature`
5. Ouvrez une **Pull Request**

### 🐛 Signaler un bug

Si vous trouvez un bug, merci d'ouvrir une [issue](../../issues) avec :
- Description détaillée du problème
- Version de WordPress et PHP
- Configuration de serveur
- Messages d'erreur le cas échéant

## 📄 Licence

Ce projet est sous licence MIT - voir le fichier [LICENSE](LICENSE) pour plus de détails.

## 👨‍💻 Auteur

**Skylide** 
- 🐙 GitHub: [@skylidefr](https://github.com/skylidefr)
- 💬 Pour toute question ou suggestion, n'hésitez pas à ouvrir une issue !

## ⭐ Remerciements

- Merci à la communauté Steam pour les retours et suggestions
- Inspiré par les outils de monitoring de serveurs de jeux existants

---

<div align="center">

**⭐ N'oubliez pas de donner une étoile si ce projet vous aide ! ⭐**

Made with ❤️ for the Steam gaming community

</div>
