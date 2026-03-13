// Haftalik Reja sahifasi JavaScript

document.addEventListener('DOMContentLoaded', function() {
    // Dastlabki yuklash
    loadYonalishlarForDropdown();
    loadHaftalarForDropdown();
    loadRejalar();
    setupRejaForm();
    setupFilters();
    setupWeekNavigation();
    setupSearch();
    updateCurrentWeekDisplay();
    
    // Joriy haftani yangilash
    updateCurrentWeek();
});

function loadRejalar() {
    const rejalar = JSON.parse(localStorage.getItem('haftalik-rejalar')) || [];
    const yonalishlar = JSON.parse(localStorage.getItem('yonalishlar')) || [];
    const tableBody = document.getElementById('rejalarTable');
    const countElement = document.getElementById('rejaCount');
    const totalBadge = document.getElementById('totalRejalar');
    
    if (tableBody) {
        tableBody.innerHTML = '';
        
        if (rejalar.length === 0) {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="8" class="text-center">
                        <div class="empty-state">
                            <i class="fas fa-calendar-week fa-3x mb-3" style="color: #ddd;"></i>
                            <h4>Rejalar topilmadi</h4>
                            <p>Birinchi rejani qo'shing</p>
                            <button class="btn btn-primary" id="addFirstReja">
                                <i class="fas fa-plus"></i> Reja qo'shish
                            </button>
                        </div>
                    </td>
                </tr>
            `;
            
            document.getElementById('addFirstReja')?.addEventListener('click', function() {
                openModal('rejaModal', 'Reja qo\'shish');
            });
        } else {
            rejalar.forEach((reja, index) => {
                const yonalish = yonalishlar.find(y => y.id === reja.yonalishId);
                const darsTuri = getDarsTuriInfo(reja.darsTuri);
                const holat = getHolatInfo(reja.holati);
                
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${index + 1}</td>
                    <td>
                        <div class="yonalish-info">
                            <strong>${yonalish ? yonalish.nomi : 'Noma\'lum'}</strong>
                            <small class="text-muted d-block">${reja.kurs}-kurs</small>
                        </div>
                    </td>
                    <td>
                        <span class="badge" style="background-color: ${darsTuri.color};">${reja.hafta}-hafta</span>
                    </td>
                    <td>
                        <strong>${reja.mavzu}</strong>
                        ${reja.tavsif ? `<br><small class="text-truncate" title="${reja.tavsif}">${reja.tavsif.substring(0, 50)}...</small>` : ''}
                    </td>
                    <td>
                        <span class="dars-turi-badge" style="background-color: ${darsTuri.color}; color: white;">
                            ${darsTuri.code} - ${darsTuri.name}
                        </span>
                    </td>
                    <td>
                        <span class="badge">${reja.vaqt || 2} soat</span>
                    </td>
                    <td>
                        <span class="holat-badge ${holat.class}">
                            ${holat.icon} ${holat.text}
                        </span>
                    </td>
                    <td>
                        <div class="action-buttons">
                            <button class="btn btn-sm btn-view" onclick="viewReja(${reja.id})" title="Ko'rish">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn btn-sm btn-edit" onclick="editReja(${reja.id})" title="Tahrirlash">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-delete" onclick="deleteReja(${reja.id})" title="O'chirish">
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
        countElement.textContent = rejalar.length;
    }
    
    if (totalBadge) {
        totalBadge.textContent = `${rejalar.length} ta reja`;
    }
    
    // Dashboard statistikasini yangilash
    // Grid ko'rinishini yangilash
    updateWeekGrid();
}

function loadYonalishlarForDropdown() {
    const yonalishlar = JSON.parse(localStorage.getItem('yonalishlar')) || [];
    const dropdowns = [
        document.getElementById('rejaYonalish'),
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

function loadHaftalarForDropdown() {
    const haftalarSelect = document.getElementById('rejaHafta');
    const filterHaftaSelect = document.getElementById('filterHafta');
    
    if (haftalarSelect) {
        for (let i = 1; i <= 16; i++) {
            const option = document.createElement('option');
            option.value = i;
            option.textContent = `${i}-hafta`;
            haftalarSelect.appendChild(option);
        }
    }
    
    if (filterHaftaSelect) {
        for (let i = 1; i <= 16; i++) {
            const option = document.createElement('option');
            option.value = i;
            option.textContent = `${i}-hafta`;
            filterHaftaSelect.appendChild(option);
        }
    }
}

function setupRejaForm() {
    const addBtn = document.getElementById('addRejaBtn');
    const saveBtn = document.getElementById('saveRejaBtn');
    const closeBtn = document.getElementById('closeRejaModal');
    const cancelBtn = document.getElementById('cancelRejaBtn');
    const exportBtn = document.getElementById('exportBtn');
    
    if (addBtn) {
        addBtn.addEventListener('click', function() {
            resetForm('rejaForm');
            document.getElementById('rejaId').value = '';
            document.getElementById('rejaModalTitle').textContent = 'Yangi reja qo\'shish';
            openModal('rejaModal', 'Yangi reja qo\'shish');
        });
    }
    
    if (saveBtn) {
        saveBtn.addEventListener('click', function() {
            saveReja();
        });
    }
    
    if (closeBtn) {
        closeBtn.addEventListener('click', function() {
            document.getElementById('rejaModal').classList.remove('show');
        });
    }
    
    if (cancelBtn) {
        cancelBtn.addEventListener('click', function() {
            document.getElementById('rejaModal').classList.remove('show');
        });
    }
    
    if (exportBtn) {
        exportBtn.addEventListener('click', function() {
            exportRejalar();
        });
    }
}

function saveReja() {
    const idInput = document.getElementById('rejaId');
    const yonalishInput = document.getElementById('rejaYonalish');
    const kursInput = document.getElementById('rejaKurs');
    const haftaInput = document.getElementById('rejaHafta');
    const darsTuriInput = document.getElementById('rejaDarsTuri');
    const mavzuInput = document.getElementById('rejaMavzu');
    const vaqtInput = document.getElementById('rejaVaqt');
    const holatiInput = document.getElementById('rejaHolati');
    const tavsifInput = document.getElementById('rejaTavsif');
    const ogituvchiInput = document.getElementById('rejaOgituvchi');
    
    // Validatsiya
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
    
    if (!haftaInput.value) {
        showMessage('Haftani tanlang!', 'error');
        haftaInput.focus();
        return;
    }
    
    if (!darsTuriInput.value) {
        showMessage('Dars turini tanlang!', 'error');
        darsTuriInput.focus();
        return;
    }
    
    if (!mavzuInput.value.trim()) {
        showMessage('Mavzuni kiriting!', 'error');
        mavzuInput.focus();
        return;
    }
    
    let rejalar = JSON.parse(localStorage.getItem('haftalik-rejalar')) || [];
    const yonalishlar = JSON.parse(localStorage.getItem('yonalishlar')) || [];
    const selectedYonalish = yonalishlar.find(y => y.id === parseInt(yonalishInput.value));
    
    if (idInput.value) {
        // Tahrirlash
        const id = parseInt(idInput.value);
        const index = rejalar.findIndex(r => r.id === id);
        
        if (index !== -1) {
            rejalar[index] = {
                ...rejalar[index],
                yonalishId: parseInt(yonalishInput.value),
                yonalishNomi: selectedYonalish ? selectedYonalish.nomi : '',
                kurs: kursInput.value,
                hafta: parseInt(haftaInput.value),
                darsTuri: darsTuriInput.value,
                mavzu: mavzuInput.value.trim(),
                vaqt: parseInt(vaqtInput.value) || 2,
                holati: holatiInput.value,
                tavsif: tavsifInput.value.trim(),
                ogituvchi: ogituvchiInput.value.trim(),
                updatedAt: new Date().toISOString()
            };
            
            showMessage('Reja muvaffaqiyatli tahrirlandi!');
        }
    } else {
        // Yangi qo'shish
        const newId = rejalar.length > 0 ? Math.max(...rejalar.map(r => r.id)) + 1 : 1;
        
        const newReja = {
            id: newId,
            yonalishId: parseInt(yonalishInput.value),
            yonalishNomi: selectedYonalish ? selectedYonalish.nomi : '',
            kurs: kursInput.value,
            hafta: parseInt(haftaInput.value),
            darsTuri: darsTuriInput.value,
            mavzu: mavzuInput.value.trim(),
            vaqt: parseInt(vaqtInput.value) || 2,
            holati: holatiInput.value || 'rejalashtirilgan',
            tavsif: tavsifInput.value.trim(),
            ogituvchi: ogituvchiInput.value.trim(),
            createdAt: new Date().toISOString(),
            updatedAt: new Date().toISOString()
        };
        
        rejalar.push(newReja);
        showMessage('Yangi reja qo\'shildi!');
    }
    
    localStorage.setItem('haftalik-rejalar', JSON.stringify(rejalar));
    
    // Modalni yopish
    document.getElementById('rejaModal').classList.remove('show');
    
    // Ro'yxatni yangilash
    loadRejalar();
}

function editReja(id) {
    const rejalar = JSON.parse(localStorage.getItem('haftalik-rejalar')) || [];
    const reja = rejalar.find(r => r.id === id);
    
    if (reja) {
        document.getElementById('rejaId').value = reja.id;
        document.getElementById('rejaYonalish').value = reja.yonalishId;
        document.getElementById('rejaKurs').value = reja.kurs;
        document.getElementById('rejaHafta').value = reja.hafta;
        document.getElementById('rejaDarsTuri').value = reja.darsTuri;
        document.getElementById('rejaMavzu').value = reja.mavzu;
        document.getElementById('rejaVaqt').value = reja.vaqt || 2;
        document.getElementById('rejaHolati').value = reja.holati;
        document.getElementById('rejaTavsif').value = reja.tavsif || '';
        document.getElementById('rejaOgituvchi').value = reja.ogituvchi || '';
        document.getElementById('rejaModalTitle').textContent = 'Rejani tahrirlash';
        
        openModal('rejaModal', 'Rejani tahrirlash');
    }
}

function viewReja(id) {
    const rejalar = JSON.parse(localStorage.getItem('haftalik-rejalar')) || [];
    const yonalishlar = JSON.parse(localStorage.getItem('yonalishlar')) || [];
    const reja = rejalar.find(r => r.id === id);
    
    if (reja) {
        const yonalish = yonalishlar.find(y => y.id === reja.yonalishId);
        const darsTuri = getDarsTuriInfo(reja.darsTuri);
        const holat = getHolatInfo(reja.holati);
        
        const detailContent = document.getElementById('rejaDetailContent');
        detailContent.innerHTML = `
            <div class="reja-header">
                <h4 class="reja-name">${reja.mavzu}</h4>
                <div class="reja-meta">
                    <span class="badge" style="background-color: ${darsTuri.color};">${darsTuri.code}</span>
                    <span class="badge badge-primary">${reja.hafta}-hafta</span>
                    <span class="holat-badge ${holat.class}">
                        ${holat.icon} ${holat.text}
                    </span>
                </div>
            </div>
            
            <div class="reja-info-grid">
                <div class="info-card">
                    <div class="info-icon">
                        <i class="fas fa-compass"></i>
                    </div>
                    <div class="info-content">
                        <h6>Yo'nalish</h6>
                        <p>${yonalish ? yonalish.nomi : 'Noma\'lum'}</p>
                        <small class="text-muted">${reja.kurs}-kurs</small>
                    </div>
                </div>
                
                <div class="info-card">
                    <div class="info-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="info-content">
                        <h6>Davomiylik</h6>
                        <p>${reja.vaqt || 2} soat</p>
                    </div>
                </div>
                
                <div class="info-card">
                    <div class="info-icon">
                        <i class="fas fa-calendar"></i>
                    </div>
                    <div class="info-content">
                        <h6>Yaratilgan sana</h6>
                        <p>${new Date(reja.createdAt).toLocaleDateString('uz-UZ')}</p>
                    </div>
                </div>
                
                <div class="info-card">
                    <div class="info-icon">
                        <i class="fas fa-user-tie"></i>
                    </div>
                    <div class="info-content">
                        <h6>O'qituvchi</h6>
                        <p>${reja.ogituvchi || 'Belgilanmagan'}</p>
                    </div>
                </div>
            </div>
            
            <div class="reja-description">
                <h5><i class="fas fa-file-alt me-2"></i>Qo'shimcha ma'lumotlar</h5>
                <div class="description-content">
                    ${reja.tavsif ? reja.tavsif.split('\n').map(paragraph => `<p>${paragraph}</p>`).join('') : '<p class="text-muted">Qo\'shimcha ma\'lumotlar kiritilmagan</p>'}
                </div>
            </div>
            
            <div class="reja-actions mt-4">
                <button class="btn btn-secondary" onclick="editReja(${reja.id})" id="editRejaFromView">
                    <i class="fas fa-edit"></i> Tahrirlash
                </button>
                <button class="btn btn-danger" onclick="deleteReja(${reja.id})">
                    <i class="fas fa-trash"></i> O'chirish
                </button>
            </div>
        `;
        
        document.getElementById('viewRejaTitle').textContent = reja.mavzu;
        
        // Edit tugmasi uchun event listener
        document.getElementById('editRejaFromView').addEventListener('click', function() {
            document.getElementById('viewRejaModal').classList.remove('show');
            editReja(reja.id);
        });
        
        // Modalni ochish
        openModal('viewRejaModal', 'Reja ma\'lumotlari');
    }
}

function deleteReja(id) {
    if (confirm('Haqiqatan ham bu rejani o\'chirmoqchimisiz? Bu harakatni ortga qaytarib bo\'lmaydi!')) {
        let rejalar = JSON.parse(localStorage.getItem('haftalik-rejalar')) || [];
        rejalar = rejalar.filter(r => r.id !== id);
        
        localStorage.setItem('haftalik-rejalar', JSON.stringify(rejalar));
        
        showMessage('Reja o\'chirildi!', 'success');
        
        // Agar view modal ochiq bo'lsa, yopish
        document.getElementById('viewRejaModal')?.classList.remove('show');
        
        // Ro'yxatni yangilash
        loadRejalar();
    }
}

function setupFilters() {
    const filterYonalish = document.getElementById('filterYonalish');
    const filterKurs = document.getElementById('filterKurs');
    const filterHafta = document.getElementById('filterHafta');
    const filterDarsTuri = document.getElementById('filterDarsTuri');
    const clearFiltersBtn = document.getElementById('clearFilters');
    
    // Filter o'zgarishlarini kuzatish
    [filterYonalish, filterKurs, filterHafta, filterDarsTuri].forEach(filter => {
        if (filter) {
            filter.addEventListener('change', function() {
                applyFilters();
            });
        }
    });
    
    // Filtrlarni tozalash
    if (clearFiltersBtn) {
        clearFiltersBtn.addEventListener('click', function() {
            filterYonalish.value = '';
            filterKurs.value = '';
            filterHafta.value = '';
            filterDarsTuri.value = '';
            applyFilters();
        });
    }
}

function applyFilters() {
    const yonalishFilter = document.getElementById('filterYonalish').value;
    const kursFilter = document.getElementById('filterKurs').value;
    const haftaFilter = document.getElementById('filterHafta').value;
    const darsTuriFilter = document.getElementById('filterDarsTuri').value;
    
    const rows = document.querySelectorAll('#rejalarTable tr');
    let visibleCount = 0;
    
    rows.forEach(row => {
        if (row.cells.length > 1) { // Bo'sh row emasligini tekshirish
            const yonalishCell = row.cells[1];
            const haftaCell = row.cells[2];
            const darsTuriCell = row.cells[4];
            
            const yonalishId = getYonalishIdFromCell(yonalishCell);
            const hafta = getHaftaFromCell(haftaCell);
            const darsTuri = getDarsTuriFromCell(darsTuriCell);
            
            let isVisible = true;
            
            if (yonalishFilter && yonalishId !== parseInt(yonalishFilter)) {
                isVisible = false;
            }
            
            if (kursFilter) {
                const kursText = yonalishCell.querySelector('.text-muted')?.textContent || '';
                const kurs = kursText.split('-')[0]; // "1-kurs" -> "1"
                if (kurs !== kursFilter) {
                    isVisible = false;
                }
            }
            
            if (haftaFilter && hafta !== parseInt(haftaFilter)) {
                isVisible = false;
            }
            
            if (darsTuriFilter && darsTuri !== darsTuriFilter) {
                isVisible = false;
            }
            
            row.style.display = isVisible ? '' : 'none';
            if (isVisible) visibleCount++;
        }
    });
    
    // Filter ma'lumotlarini yangilash
    updateRejaFilterInfo(visibleCount);
}

function getYonalishIdFromCell(cell) {
    const yonalishName = cell.querySelector('strong')?.textContent;
    if (yonalishName) {
        const yonalishlar = JSON.parse(localStorage.getItem('yonalishlar')) || [];
        const yonalish = yonalishlar.find(y => y.nomi === yonalishName);
        return yonalish ? yonalish.id : null;
    }
    return null;
}

function getHaftaFromCell(cell) {
    const badge = cell.querySelector('.badge');
    if (badge) {
        const text = badge.textContent;
        const haftaMatch = text.match(/(\d+)-hafta/);
        return haftaMatch ? parseInt(haftaMatch[1]) : null;
    }
    return null;
}

function getDarsTuriFromCell(cell) {
    const badge = cell.querySelector('.dars-turi-badge');
    if (badge) {
        const text = badge.textContent;
        const codeMatch = text.match(/^(\w+)/);
        return codeMatch ? codeMatch[1] : null;
    }
    return null;
}

function updateRejaFilterInfo(visibleCount) {
    const filterInfo = document.getElementById('rejaFilterInfo');
    const totalCount = (JSON.parse(localStorage.getItem('haftalik-rejalar')) || []).length;
    
    if (filterInfo) {
        const filters = [];
        
        const yonalishFilter = document.getElementById('filterYonalish').value;
        const kursFilter = document.getElementById('filterKurs').value;
        const haftaFilter = document.getElementById('filterHafta').value;
        const darsTuriFilter = document.getElementById('filterDarsTuri').value;
        
        if (yonalishFilter) {
            const yonalishlar = JSON.parse(localStorage.getItem('yonalishlar')) || [];
            const yonalish = yonalishlar.find(y => y.id === parseInt(yonalishFilter));
            if (yonalish) filters.push(`Yo'nalish: ${yonalish.nomi}`);
        }
        
        if (kursFilter) filters.push(`Kurs: ${kursFilter}`);
        if (haftaFilter) filters.push(`Hafta: ${haftaFilter}`);
        if (darsTuriFilter) {
            const darsTuri = getDarsTuriInfo(darsTuriFilter);
            filters.push(`Dars turi: ${darsTuri.name}`);
        }
        
        if (filters.length > 0) {
            filterInfo.innerHTML = `
                <span class="text-primary">
                    <i class="fas fa-filter"></i> Filtrlar: ${filters.join(', ')}
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

function setupWeekNavigation() {
    const prevWeekBtn = document.getElementById('prevWeek');
    const nextWeekBtn = document.getElementById('nextWeek');
    
    let currentWeek = 1;
    
    if (prevWeekBtn) {
        prevWeekBtn.addEventListener('click', function() {
            currentWeek = Math.max(1, currentWeek - 1);
            updateCurrentWeekDisplay();
            updateWeekGrid();
        });
    }
    
    if (nextWeekBtn) {
        nextWeekBtn.addEventListener('click', function() {
            currentWeek = Math.min(16, currentWeek + 1);
            updateCurrentWeekDisplay();
            updateWeekGrid();
        });
    }
    
    window.getCurrentWeek = function() {
        return currentWeek;
    };
}

function updateCurrentWeekDisplay() {
    const currentWeek = window.getCurrentWeek ? window.getCurrentWeek() : 1;
    const weekDisplay = document.getElementById('currentWeekDisplay');
    const weekDates = document.getElementById('weekDates');
    
    if (weekDisplay) {
        weekDisplay.textContent = `${currentWeek}-hafta`;
    }
    
    if (weekDates) {
        // Haftaning boshlanish va tugash sanasini hisoblash
        const today = new Date();
        const startDate = new Date(today);
        startDate.setDate(today.getDate() - today.getDay() + 1 + (currentWeek - 1) * 7);
        
        const endDate = new Date(startDate);
        endDate.setDate(startDate.getDate() + 6);
        
        const options = { month: 'long', day: 'numeric' };
        const startStr = startDate.toLocaleDateString('uz-UZ', options);
        const endStr = endDate.toLocaleDateString('uz-UZ', options);
        
        weekDates.textContent = `${startStr} - ${endStr}`;
    }
}

function updateWeekGrid() {
    const currentWeek = window.getCurrentWeek ? window.getCurrentWeek() : 1;
    const gridContainer = document.getElementById('weekGrid');
    const rejalar = JSON.parse(localStorage.getItem('haftalik-rejalar')) || [];
    
    if (gridContainer) {
        // Hozirgi haftaning rejalarini filtrlash
        const weekRejalar = rejalar.filter(r => r.hafta === currentWeek);
        
        if (weekRejalar.length === 0) {
            gridContainer.innerHTML = `
                <div class="empty-grid">
                    <i class="fas fa-calendar-times fa-3x mb-3" style="color: #ddd;"></i>
                    <h5>Bu hafta uchun rejalar mavjud emas</h5>
                    <p>Reja qo'shish yoki boshqa haftani tanlang</p>
                    <button class="btn btn-primary" id="addWeekReja">
                        <i class="fas fa-plus"></i> Reja qo'shish
                    </button>
                </div>
            `;
            
            document.getElementById('addWeekReja')?.addEventListener('click', function() {
                openModal('rejaModal', 'Reja qo\'shish');
            });
        } else {
            // Rejalarni kunlar bo'yicha guruhlash
            const kunlar = ['Dushanba', 'Seshanba', 'Chorshanba', 'Payshanba', 'Juma', 'Shanba', 'Yakshanba'];
            
            gridContainer.innerHTML = '';
            
            kunlar.forEach((kun, kunIndex) => {
                const kunRejalar = weekRejalar.filter((reja, index) => index % 7 === kunIndex);
                
                const kunColumn = document.createElement('div');
                kunColumn.className = 'grid-column';
                kunColumn.innerHTML = `
                    <div class="grid-day-header">
                        <h5>${kun}</h5>
                        <small>${kunIndex + 1}-kun</small>
                    </div>
                    <div class="grid-items" id="gridDay${kunIndex + 1}">
                        ${kunRejalar.length === 0 ? 
                            `<div class="grid-empty">Rejalar yo'q</div>` : 
                            kunRejalar.map(reja => createGridItem(reja)).join('')
                        }
                    </div>
                `;
                
                gridContainer.appendChild(kunColumn);
            });
        }
    }
}

function createGridItem(reja) {
    const darsTuri = getDarsTuriInfo(reja.darsTuri);
    
    return `
        <div class="grid-item" style="border-left-color: ${darsTuri.color};" onclick="viewReja(${reja.id})">
            <div class="grid-item-header">
                <span class="grid-item-type" style="background-color: ${darsTuri.color};">${darsTuri.code}</span>
                <span class="grid-item-time">${reja.vaqt || 2} soat</span>
            </div>
            <div class="grid-item-body">
                <h6>${reja.mavzu}</h6>
                <small class="grid-item-yonalish">${reja.yonalishNomi}</small>
            </div>
            <div class="grid-item-footer">
                <span class="grid-item-kurs">${reja.kurs}-kurs</span>
                <span class="grid-item-status ${getHolatInfo(reja.holati).class}">
                    ${getHolatInfo(reja.holati).icon}
                </span>
            </div>
        </div>
    `;
}

function setupSearch() {
    const searchInput = document.getElementById('searchReja');
    
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('#rejalarTable tr');
            let visibleCount = 0;
            
            rows.forEach(row => {
                if (row.cells.length > 1) {
                    const text = row.textContent.toLowerCase();
                    const isVisible = text.includes(searchTerm);
                    row.style.display = isVisible ? '' : 'none';
                    if (isVisible) visibleCount++;
                }
            });
            
            // Filter ma'lumotlarini yangilash
            updateRejaFilterInfo(visibleCount);
        });
    }
}

function updateCurrentWeek() {
    const currentWeekElement = document.getElementById('currentWeek');
    if (currentWeekElement) {
        // Haqiqiy hafta raqamini hisoblash
        const now = new Date();
        const start = new Date(now.getFullYear(), 0, 1);
        const days = Math.floor((now - start) / (24 * 60 * 60 * 1000));
        const weekNumber = Math.ceil((now.getDay() + 1 + days) / 7);
        
        currentWeekElement.textContent = `Hozirgi hafta: ${weekNumber}`;
    }
}

function exportRejalar() {
    const rejalar = JSON.parse(localStorage.getItem('haftalik-rejalar')) || [];
    const yonalishlar = JSON.parse(localStorage.getItem('yonalishlar')) || [];
    
    if (rejalar.length === 0) {
        showMessage('Eksport qilish uchun rejalar mavjud emas!', 'error');
        return;
    }
    
    // CSV formatga o'tkazish
    let csvContent = "Yo'nalish,Kurs,Hafta,Dars turi,Mavzu,Vaqt,Holati,O'qituvchi,Tavsif\n";
    
    rejalar.forEach(reja => {
        const yonalish = yonalishlar.find(y => y.id === reja.yonalishId);
        const darsTuri = getDarsTuriInfo(reja.darsTuri);
        
        const row = [
            yonalish ? yonalish.nomi : 'Noma\'lum',
            reja.kurs,
            reja.hafta,
            darsTuri.name,
            `"${reja.mavzu.replace(/"/g, '""')}"`,
            reja.vaqt || 2,
            getHolatInfo(reja.holati).text,
            reja.ogituvchi || '',
            `"${(reja.tavsif || '').replace(/"/g, '""')}"`
        ];
        
        csvContent += row.join(',') + "\n";
    });
    
    // CSV faylni yuklab olish
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    
    link.setAttribute('href', url);
    link.setAttribute('download', `haftalik_rejalar_${new Date().toISOString().split('T')[0]}.csv`);
    link.style.visibility = 'hidden';
    
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    showMessage('Rejalar CSV formatida yuklab olindi!');
}

// Yordamchi funksiyalar
function getDarsTuriInfo(code) {
    const darsTurlari = {
        'A': { code: 'A', name: 'Amaliyot', color: '#2ecc71' },
        'T': { code: 'T', name: 'Teoriya', color: '#3498db' },
        'M': { code: 'M', name: 'Mustaqil ish', color: '#9b59b6' },
        'B': { code: 'B', name: 'Bajarilishi', color: '#f39c12' },
        'D': { code: 'D', name: 'Darslik', color: '#e74c3c' },
        'TT': { code: 'T/TY', name: 'Test/Tekshiruv', color: '#1abc9c' },
        'G': { code: 'G', name: 'Guruh ishi', color: '#34495e' }
    };
    
    return darsTurlari[code] || { code, name: 'Noma\'lum', color: '#95a5a6' };
}

function getHolatInfo(holat) {
    const holatlar = {
        'rejalashtirilgan': { text: 'Rejalashtirilgan', class: 'holat-rejalashtirilgan', icon: '<i class="fas fa-clock"></i>' },
        'bajarilgan': { text: 'Bajarilgan', class: 'holat-bajarilgan', icon: '<i class="fas fa-check-circle"></i>' },
        'kechiktirilgan': { text: 'Kechiktirilgan', class: 'holat-kechiktirilgan', icon: '<i class="fas fa-exclamation-triangle"></i>' },
        'bekor_qilingan': { text: 'Bekor qilingan', class: 'holat-bekor', icon: '<i class="fas fa-times-circle"></i>' }
    };
    
    return holatlar[holat] || { text: 'Noma\'lum', class: 'holat-noma\'lum', icon: '<i class="fas fa-question-circle"></i>' };
}

