// Dasturlar sahifasi JavaScript

document.addEventListener('DOMContentLoaded', function() {
    loadDasturlar();
    loadYonalishlarForDropdown();
    setupDasturForm();
    setupSearch();
    setupFilter();
    loadStatistics();
});

function loadDasturlar() {
    const dasturlar = JSON.parse(localStorage.getItem('dasturlar')) || [];
    const yonalishlar = JSON.parse(localStorage.getItem('yonalishlar')) || [];
    const tableBody = document.getElementById('dasturlarTable');
    const countElement = document.getElementById('dasturCount');
    const totalBadge = document.getElementById('totalDasturlar');
    
    if (tableBody) {
        tableBody.innerHTML = '';
        
        if (dasturlar.length === 0) {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center">
                        <div class="empty-state">
                            <i class="fas fa-book fa-3x mb-3" style="color: #ddd;"></i>
                            <h4>Dasturlar topilmadi</h4>
                            <p>Birinchi dasturni qo'shing</p>
                            <button class="btn btn-primary" id="addFirstDastur">
                                <i class="fas fa-plus"></i> Dastur qo'shish
                            </button>
                        </div>
                    </td>
                </tr>
            `;
            
            document.getElementById('addFirstDastur')?.addEventListener('click', function() {
                openModal('dasturModal', 'Dastur qo\'shish');
            });
        } else {
            dasturlar.forEach((dastur, index) => {
                const yonalish = yonalishlar.find(y => y.id === dastur.yonalishId);
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${index + 1}</td>
                    <td>
                        <strong>${dastur.nomi}</strong>
                        <div class="dastur-code">ID: DST-${dastur.id.toString().padStart(3, '0')}</div>
                    </td>
                    <td>
                        ${dastur.tavsif ? `<p class="text-truncate" style="max-width: 200px;" title="${dastur.tavsif}">${dastur.tavsif.substring(0, 60)}...</p>` : '<span class="text-muted">Tavsif yo\'q</span>'}
                    </td>
                    <td>
                        ${yonalish ? `
                            <div class="yonalish-info">
                                <strong>${yonalish.nomi}</strong>
                                <small class="text-muted d-block">${yonalish.kurs}-kurs</small>
                            </div>
                        ` : '<span class="text-danger">Yo\'nalish topilmadi</span>'}
                    </td>
                    <td>
                        <span class="badge">${dastur.kurs}-kurs</span>
                    </td>
                    <td>${new Date(dastur.createdAt).toLocaleDateString('uz-UZ')}</td>
                    <td>
                        <div class="action-buttons">
                            <button class="btn btn-sm btn-view" onclick="viewDastur(${dastur.id})" title="Ko'rish">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn btn-sm btn-edit" onclick="editDastur(${dastur.id})" title="Tahrirlash">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-delete" onclick="deleteDastur(${dastur.id})" title="O'chirish">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                `;
                tableBody.appendChild(row);
            });
        }
    }
    
    if (countElement) {
        countElement.textContent = dasturlar.length;
    }
    
    if (totalBadge) {
        totalBadge.textContent = `${dasturlar.length} ta`;
    }
    
    // Dashboard statistikasini yangilash
    // updateStats();
}

function loadYonalishlarForDropdown() {
    const yonalishlar = JSON.parse(localStorage.getItem('yonalishlar')) || [];
    const dropdowns = [
        document.getElementById('dasturYonalish'),
        document.getElementById('filterYonalish')
    ];
    
    dropdowns.forEach(dropdown => {
        if (dropdown) {
            // Hozirgi optionlarni saqlash
            const currentValue = dropdown.value;
            dropdown.innerHTML = '<option value="">' + (dropdown.id === 'filterYonalish' ? 'Barcha yo\'nalishlar' : 'Yo\'nalishni tanlang') + '</option>';
            
            yonalishlar.forEach(yonalish => {
                const option = document.createElement('option');
                option.value = yonalish.id;
                option.textContent = `${yonalish.nomi} (${yonalish.kurs}-kurs)`;
                dropdown.appendChild(option);
            });
            
            // Oldingi qiymatni tiklash
            dropdown.value = currentValue;
        }
    });
}

function setupDasturForm() {
    const addBtn = document.getElementById('addDasturBtn');
    const saveBtn = document.getElementById('saveDasturBtn');
    const closeBtn = document.getElementById('closeDasturModal');
    const cancelBtn = document.getElementById('cancelDasturBtn');
    
    if (addBtn) {
        addBtn.addEventListener('click', function() {
            resetForm('dasturForm');
            document.getElementById('dasturId').value = '';
            document.getElementById('dasturModalTitle').textContent = 'Yangi dastur qo\'shish';
            openModal('dasturModal', 'Yangi dastur qo\'shish');
        });
    }
    
    if (saveBtn) {
        saveBtn.addEventListener('click', function() {
            saveDastur();
        });
    }
    
    if (closeBtn) {
        closeBtn.addEventListener('click', function() {
            document.getElementById('dasturModal').classList.remove('show');
        });
    }
    
    if (cancelBtn) {
        cancelBtn.addEventListener('click', function() {
            document.getElementById('dasturModal').classList.remove('show');
        });
    }
}

function saveDastur() {
    const idInput = document.getElementById('dasturId');
    const nomiInput = document.getElementById('dasturNomi');
    const yonalishInput = document.getElementById('dasturYonalish');
    const kursInput = document.getElementById('dasturKurs');
    const tavsifiInput = document.getElementById('dasturTavsifi');
    const kreditInput = document.getElementById('dasturKredit');
    
    // Validatsiya
    if (!nomiInput.value.trim()) {
        showMessage('Dastur nomini kiriting!', 'error');
        nomiInput.focus();
        return;
    }
    
    if (!yonalishInput.value) {
        showMessage('Yo\'nalishni tanlang!', 'error');
        yonalishInput.focus();
        return;
    }
    
    if (!kursInput.value) {
        showMessage('Kursni tanlang!', 'error');
        kursInput.focus();
        return;
    }
    
    if (!tavsifiInput.value.trim()) {
        showMessage('Dastur tavsifini kiriting!', 'error');
        tavsifiInput.focus();
        return;
    }
    
    let dasturlar = JSON.parse(localStorage.getItem('dasturlar')) || [];
    const yonalishlar = JSON.parse(localStorage.getItem('yonalishlar')) || [];
    const selectedYonalish = yonalishlar.find(y => y.id === parseInt(yonalishInput.value));
    
    if (idInput.value) {
        // Tahrirlash
        const id = parseInt(idInput.value);
        const index = dasturlar.findIndex(d => d.id === id);
        
        if (index !== -1) {
            dasturlar[index] = {
                ...dasturlar[index],
                nomi: nomiInput.value.trim(),
                yonalishId: parseInt(yonalishInput.value),
                yonalishNomi: selectedYonalish ? selectedYonalish.nomi : '',
                kurs: kursInput.value,
                tavsif: tavsifiInput.value.trim(),
                kredit: parseInt(kreditInput.value) || 3,
                updatedAt: new Date().toISOString()
            };
            
            showMessage('Dastur muvaffaqiyatli tahrirlandi!');
        }
    } else {
        // Yangi qo'shish
        const newId = dasturlar.length > 0 ? Math.max(...dasturlar.map(d => d.id)) + 1 : 1;
        
        const newDastur = {
            id: newId,
            nomi: nomiInput.value.trim(),
            yonalishId: parseInt(yonalishInput.value),
            yonalishNomi: selectedYonalish ? selectedYonalish.nomi : '',
            kurs: kursInput.value,
            tavsif: tavsifiInput.value.trim(),
            kredit: parseInt(kreditInput.value) || 3,
            createdAt: new Date().toISOString(),
            updatedAt: new Date().toISOString()
        };
        
        dasturlar.push(newDastur);
        showMessage('Yangi dastur qo\'shildi!');
    }
    
    localStorage.setItem('dasturlar', JSON.stringify(dasturlar));
    
    // Modalni yopish
    document.getElementById('dasturModal').classList.remove('show');
    
    // Ro'yxatni yangilash
    loadDasturlar();
    loadStatistics();
}

function editDastur(id) {
    const dasturlar = JSON.parse(localStorage.getItem('dasturlar')) || [];
    const dastur = dasturlar.find(d => d.id === id);
    
    if (dastur) {
        document.getElementById('dasturId').value = dastur.id;
        document.getElementById('dasturNomi').value = dastur.nomi;
        document.getElementById('dasturYonalish').value = dastur.yonalishId;
        document.getElementById('dasturKurs').value = dastur.kurs;
        document.getElementById('dasturTavsifi').value = dastur.tavsif;
        document.getElementById('dasturKredit').value = dastur.kredit || 3;
        document.getElementById('dasturModalTitle').textContent = 'Dasturni tahrirlash';
        
        openModal('dasturModal', 'Dasturni tahrirlash');
    }
}

function viewDastur(id) {
    const dasturlar = JSON.parse(localStorage.getItem('dasturlar')) || [];
    const yonalishlar = JSON.parse(localStorage.getItem('yonalishlar')) || [];
    const dastur = dasturlar.find(d => d.id === id);
    
    if (dastur) {
        const yonalish = yonalishlar.find(y => y.id === dastur.yonalishId);
        
        const detailContent = document.getElementById('dasturDetailContent');
        detailContent.innerHTML = `
            <div class="dastur-header">
                <h4 class="dastur-name">${dastur.nomi}</h4>
                <div class="dastur-meta">
                    <span class="badge badge-primary">DST-${dastur.id.toString().padStart(3, '0')}</span>
                    <span class="badge">${dastur.kurs}-kurs</span>
                    <span class="badge badge-success">${dastur.kredit || 3} kredit</span>
                </div>
            </div>
            
            <div class="dastur-info-grid">
                <div class="info-card">
                    <div class="info-icon">
                        <i class="fas fa-compass"></i>
                    </div>
                    <div class="info-content">
                        <h6>Yo'nalish</h6>
                        <p>${yonalish ? yonalish.nomi : 'Noma\'lum'}</p>
                    </div>
                </div>
                
                <div class="info-card">
                    <div class="info-icon">
                        <i class="fas fa-calendar"></i>
                    </div>
                    <div class="info-content">
                        <h6>Yaratilgan sana</h6>
                        <p>${new Date(dastur.createdAt).toLocaleDateString('uz-UZ')}</p>
                    </div>
                </div>
                
                <div class="info-card">
                    <div class="info-icon">
                        <i class="fas fa-history"></i>
                    </div>
                    <div class="info-content">
                        <h6>Yangilangan</h6>
                        <p>${new Date(dastur.updatedAt || dastur.createdAt).toLocaleDateString('uz-UZ')}</p>
                    </div>
                </div>
            </div>
            
            <div class="dastur-description">
                <h5><i class="fas fa-file-alt me-2"></i>Dastur tavsifi</h5>
                <div class="description-content">
                    ${dastur.tavsif.split('\n').map(paragraph => `<p>${paragraph}</p>`).join('')}
                </div>
            </div>
            
            <div class="dastur-actions mt-4">
                <button class="btn btn-secondary" onclick="editDastur(${dastur.id})" id="editFromViewBtn">
                    <i class="fas fa-edit"></i> Tahrirlash
                </button>
                <button class="btn btn-danger" onclick="deleteDastur(${dastur.id})">
                    <i class="fas fa-trash"></i> O'chirish
                </button>
            </div>
        `;
        
        document.getElementById('viewDasturTitle').textContent = dastur.nomi;
        
        // Edit tugmasi uchun event listener
        document.getElementById('editFromViewBtn').addEventListener('click', function() {
            document.getElementById('viewDasturModal').classList.remove('show');
            editDastur(dastur.id);
        });
        
        // Modalni ochish
        openModal('viewDasturModal', 'Dastur ma\'lumotlari');
    }
}

function deleteDastur(id) {
    if (confirm('Haqiqatan ham bu dasturni o\'chirmoqchimisiz? Bu harakatni ortga qaytarib bo\'lmaydi!')) {
        let dasturlar = JSON.parse(localStorage.getItem('dasturlar')) || [];
        dasturlar = dasturlar.filter(d => d.id !== id);
        
        localStorage.setItem('dasturlar', JSON.stringify(dasturlar));
        
        showMessage('Dastur o\'chirildi!', 'success');
        
        // Agar view modal ochiq bo'lsa, yopish
        document.getElementById('viewDasturModal')?.classList.remove('show');
        
        // Ro'yxatni yangilash
        loadDasturlar();
        loadStatistics();
    }
}

function setupSearch() {
    const searchInput = document.getElementById('searchDastur');
    
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('#dasturlarTable tr');
            let visibleCount = 0;
            
            rows.forEach(row => {
                if (row.cells.length > 1) { // Bo'sh row emasligini tekshirish
                    const text = row.textContent.toLowerCase();
                    const isVisible = text.includes(searchTerm);
                    row.style.display = isVisible ? '' : 'none';
                    if (isVisible) visibleCount++;
                }
            });
            
            // Filter ma'lumotlarini yangilash
            updateFilterInfo(visibleCount);
        });
    }
}

function setupFilter() {
    const filterSelect = document.getElementById('filterYonalish');
    
    if (filterSelect) {
        filterSelect.addEventListener('change', function() {
            const filterValue = this.value;
            const rows = document.querySelectorAll('#dasturlarTable tr');
            let visibleCount = 0;
            
            rows.forEach(row => {
                if (row.cells.length > 1) {
                    if (!filterValue) {
                        row.style.display = '';
                        visibleCount++;
                    } else {
                        const yonalishCell = row.cells[3];
                        const yonalishId = getYonalishIdFromCell(yonalishCell);
                        const isVisible = yonalishId === parseInt(filterValue);
                        row.style.display = isVisible ? '' : 'none';
                        if (isVisible) visibleCount++;
                    }
                }
            });
            
            updateFilterInfo(visibleCount, filterValue);
        });
    }
}

function getYonalishIdFromCell(cell) {
    const yonalishInfo = cell.querySelector('.yonalish-info');
    if (yonalishInfo) {
        const yonalishName = yonalishInfo.querySelector('strong').textContent;
        const yonalishlar = JSON.parse(localStorage.getItem('yonalishlar')) || [];
        const yonalish = yonalishlar.find(y => y.nomi === yonalishName);
        return yonalish ? yonalish.id : null;
    }
    return null;
}

function updateFilterInfo(visibleCount, filterValue = null) {
    const filterInfo = document.getElementById('filterInfo');
    const totalCount = (JSON.parse(localStorage.getItem('dasturlar')) || []).length;
    
    if (filterInfo) {
        if (filterValue) {
            const yonalishlar = JSON.parse(localStorage.getItem('yonalishlar')) || [];
            const yonalish = yonalishlar.find(y => y.id === parseInt(filterValue));
            filterInfo.innerHTML = `
                <span class="text-primary">
                    <i class="fas fa-filter"></i> Filtr: ${yonalish ? yonalish.nomi : 'Noma\'lum'}
                </span>
                <span class="ms-3">Ko'rsatilmoqda: ${visibleCount} / ${totalCount}</span>
            `;
        } else {
            filterInfo.innerHTML = `
                <span class="text-muted">
                    <i class="fas fa-filter"></i> Filtr yo'q
                </span>
                <span class="ms-3">Jami: ${totalCount}</span>
            `;
        }
    }
}

function loadStatistics() {
    const dasturlar = JSON.parse(localStorage.getItem('dasturlar')) || [];
    const yonalishlar = JSON.parse(localStorage.getItem('yonalishlar')) || [];
    const statsContainer = document.getElementById('yonalishStats');
    
    if (statsContainer) {
        statsContainer.innerHTML = '';
        
        if (yonalishlar.length === 0) {
            statsContainer.innerHTML = `
                <div class="empty-stats">
                    <i class="fas fa-chart-bar fa-2x mb-2" style="color: #ddd;"></i>
                    <p>Statistika uchun yo'nalishlar mavjud emas</p>
                </div>
            `;
            return;
        }
        
        // Har bir yo'nalish bo'yicha dasturlar sonini hisoblash
        const stats = {};
        yonalishlar.forEach(yonalish => {
            stats[yonalish.id] = {
                name: yonalish.nomi,
                count: 0,
                kurs: yonalish.kurs
            };
        });
        
        dasturlar.forEach(dastur => {
            if (stats[dastur.yonalishId]) {
                stats[dastur.yonalishId].count++;
            }
        });
        
        // Statistikani chiqarish
        Object.values(stats).forEach(stat => {
            const percentage = yonalishlar.length > 0 ? Math.round((stat.count / dasturlar.length) * 100) : 0;
            
            const statCard = document.createElement('div');
            statCard.className = 'stat-card-small';
            statCard.innerHTML = `
                <div class="stat-small-header">
                    <h4>${stat.name}</h4>
                    <span class="badge">${stat.kurs}-kurs</span>
                </div>
                <div class="stat-small-body">
                    <div class="stat-number">${stat.count}</div>
                    <div class="stat-label">dastur</div>
                </div>
                <div class="stat-progress">
                    <div class="progress-bar" style="width: ${percentage}%"></div>
                </div>
                <div class="stat-small-footer">
                    <span>${percentage}% • Jami dasturlardan</span>
                </div>
            `;
            
            statsContainer.appendChild(statCard);
        });
    }
}

// Modalni ochish va yopish funksiyalari
function openModal(modalId, title = '') {
    const modal = document.getElementById(modalId);
    const modalTitle = document.getElementById(modalId + 'Title') || 
                      document.getElementById('viewDasturTitle') || 
                      document.getElementById('modalTitle');
    
    if (modal) {
        if (modalTitle && title) {
            modalTitle.textContent = title;
        }
        modal.classList.add('show');
    }
}
