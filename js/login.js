// WhatsJuju Chat - Login JavaScript

// Alternar visibilidade da senha
function togglePassword(element) {
    const input = element.parentElement.querySelector('input[type="password"], input[type="text"]');
    const icon = element.querySelector('i');
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
    
    // Efeito visual
    element.style.transform = 'translateY(-50%) scale(1.2)';
    setTimeout(() => {
        element.style.transform = 'translateY(-50%) scale(1)';
    }, 150);
}

// Mostrar formulário de registro
function showRegisterForm() {
    const loginForm = document.getElementById('loginForm');
    const registerForm = document.getElementById('registerForm');
    
    // Animação de saída do login
    loginForm.style.animation = 'slideOutLeft 0.3s ease-in';
    
    setTimeout(() => {
        loginForm.style.display = 'none';
        registerForm.style.display = 'block';
        registerForm.style.animation = 'slideInRight 0.3s ease-out';
    }, 300);
}

// Mostrar formulário de login
function showLoginForm() {
    const loginForm = document.getElementById('loginForm');
    const registerForm = document.getElementById('registerForm');
    
    // Animação de saída do registro
    registerForm.style.animation = 'slideOutRight 0.3s ease-in';
    
    setTimeout(() => {
        registerForm.style.display = 'none';
        loginForm.style.display = 'block';
        loginForm.style.animation = 'slideInLeft 0.3s ease-out';
    }, 300);
}

// Adicionar animações CSS dinamicamente
const style = document.createElement('style');
style.textContent = `
    @keyframes slideOutLeft {
        from { opacity: 1; transform: translateX(0); }
        to { opacity: 0; transform: translateX(-100%); }
    }
    
    @keyframes slideInRight {
        from { opacity: 0; transform: translateX(100%); }
        to { opacity: 1; transform: translateX(0); }
    }
    
    @keyframes slideOutRight {
        from { opacity: 1; transform: translateX(0); }
        to { opacity: 0; transform: translateX(100%); }
    }
    
    @keyframes slideInLeft {
        from { opacity: 0; transform: translateX(-100%); }
        to { opacity: 1; transform: translateX(0); }
    }
`;
document.head.appendChild(style);

// Efeitos de entrada nos inputs
document.addEventListener('DOMContentLoaded', function() {
    const inputs = document.querySelectorAll('input');
    
    inputs.forEach(input => {
        // Efeito de foco
        input.addEventListener('focus', function() {
            this.parentElement.style.transform = 'scale(1.02)';
        });
        
        // Efeito de blur
        input.addEventListener('blur', function() {
            this.parentElement.style.transform = 'scale(1)';
        });
        
        // Validação em tempo real
        input.addEventListener('input', function() {
            if (this.value.length > 0) {
                this.style.borderColor = '#96ceb4';
                this.style.backgroundColor = 'rgba(168, 230, 207, 0.1)';
            } else {
                this.style.borderColor = 'rgba(255, 107, 157, 0.2)';
                this.style.backgroundColor = 'rgba(255, 255, 255, 0.9)';
            }
        });
    });
    
    // Efeito nos botões
    const buttons = document.querySelectorAll('.btn');
    buttons.forEach(button => {
        button.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-3px) scale(1.02)';
        });
        
        button.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0) scale(1)';
        });
    });
    
    // Efeito no checkbox
    const checkbox = document.querySelector('input[type="checkbox"]');
    if (checkbox) {
        checkbox.addEventListener('change', function() {
            const checkmark = this.nextElementSibling;
            if (this.checked) {
                checkmark.style.transform = 'scale(1.1)';
                setTimeout(() => {
                    checkmark.style.transform = 'scale(1)';
                }, 150);
            }
        });
    }
    
    // Adicionar efeitos de partículas nos cliques
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('btn')) {
            createClickEffect(e.target, e.clientX, e.clientY);
        }
    });
});

// Criar efeito de clique com partículas
function createClickEffect(element, x, y) {
    const colors = ['#ff6b9d', '#4ecdc4', '#45b7d1', '#96ceb4', '#ffd93d'];
    
    for (let i = 0; i < 6; i++) {
        const particle = document.createElement('div');
        particle.style.position = 'fixed';
        particle.style.left = x + 'px';
        particle.style.top = y + 'px';
        particle.style.width = '6px';
        particle.style.height = '6px';
        particle.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
        particle.style.borderRadius = '50%';
        particle.style.pointerEvents = 'none';
        particle.style.zIndex = '9999';
        
        document.body.appendChild(particle);
        
        const angle = (Math.PI * 2 * i) / 6;
        const velocity = 100;
        const vx = Math.cos(angle) * velocity;
        const vy = Math.sin(angle) * velocity;
        
        let opacity = 1;
        let scale = 1;
        
        const animate = () => {
            const rect = particle.getBoundingClientRect();
            particle.style.left = (rect.left + vx * 0.02) + 'px';
            particle.style.top = (rect.top + vy * 0.02) + 'px';
            
            opacity -= 0.02;
            scale -= 0.02;
            
            particle.style.opacity = opacity;
            particle.style.transform = `scale(${scale})`;
            
            if (opacity > 0) {
                requestAnimationFrame(animate);
            } else {
                document.body.removeChild(particle);
            }
        };
        
        requestAnimationFrame(animate);
    }
}

// Validação de formulário
function validateForm(form) {
    const inputs = form.querySelectorAll('input[required]');
    let isValid = true;
    
    inputs.forEach(input => {
        if (!input.value.trim()) {
            input.style.borderColor = '#ff9a9e';
            input.style.backgroundColor = 'rgba(255, 154, 158, 0.1)';
            isValid = false;
            
            // Efeito de shake
            input.style.animation = 'shake 0.5s ease-in-out';
            setTimeout(() => {
                input.style.animation = '';
            }, 500);
        }
    });
    
    return isValid;
}

// Adicionar animação de shake
const shakeStyle = document.createElement('style');
shakeStyle.textContent = `
    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
        20%, 40%, 60%, 80% { transform: translateX(5px); }
    }
`;
document.head.appendChild(shakeStyle);

// Interceptar envio de formulários para validação
document.addEventListener('DOMContentLoaded', function() {
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!validateForm(this)) {
                e.preventDefault();
                
                // Mostrar mensagem de erro temporária
                showTemporaryMessage('Por favor, preencha todos os campos obrigatórios!', 'error');
            }
        });
    });
});

// Mostrar mensagem temporária
function showTemporaryMessage(message, type) {
    const existingAlert = document.querySelector('.temp-alert');
    if (existingAlert) {
        existingAlert.remove();
    }
    
    const alert = document.createElement('div');
    alert.className = `alert alert-${type} temp-alert`;
    alert.innerHTML = `<i class="fas fa-exclamation-triangle"></i> ${message}`;
    alert.style.position = 'fixed';
    alert.style.top = '20px';
    alert.style.left = '50%';
    alert.style.transform = 'translateX(-50%)';
    alert.style.zIndex = '10000';
    alert.style.animation = 'slideInDown 0.3s ease-out';
    
    document.body.appendChild(alert);
    
    setTimeout(() => {
        alert.style.animation = 'slideOutUp 0.3s ease-in';
        setTimeout(() => {
            if (alert.parentNode) {
                alert.parentNode.removeChild(alert);
            }
        }, 300);
    }, 3000);
}

// Adicionar animações para mensagens temporárias
const messageStyle = document.createElement('style');
messageStyle.textContent = `
    @keyframes slideInDown {
        from { opacity: 0; transform: translateX(-50%) translateY(-100%); }
        to { opacity: 1; transform: translateX(-50%) translateY(0); }
    }
    
    @keyframes slideOutUp {
        from { opacity: 1; transform: translateX(-50%) translateY(0); }
        to { opacity: 0; transform: translateX(-50%) translateY(-100%); }
    }
`;
document.head.appendChild(messageStyle);

