@extends('layouts.admin')

@section('navbar-right')
@if(session('user.is_admin'))
<a href="{{ route('dashboard') }}" class="btn btn-outline-secondary mr-2">Return To Main</a>
@endif
<form action="{{ route('logout') }}" method="post" class="d-inline">
    @csrf
    <button type="submit" class="btn btn-outline-secondary">Logout</button>
</form>
@endsection

@section('content')
<div class="admin-config-page-con">
    <div class="admin-config-visual-panel">
        <div class="admin-config-visual-inner">
            <h1 class="admin_config-title">Configurations</h1>
                <div class="admin-config-btn-col">
                    <button >Latest Bill Range</button>
                    <button>Bill Arears Quota</button>
                    <button>User Account</button>
                </div>

            <div class="admin-config-ranges-visual-panel">
            <div class="admin-config-ranges-visual-inner">
                <div class="admin-config-right-side">

            <div class="admin-config-user-card">
                <div class="card">
                    <div>
                        <div class="numbers">100</div>
                        <div class="cardName">Upper Range</div>
                    </div>

                    <div class="iconBx">
                        <ion-icon name="people-circle-outline"></ion-icon>
                    </div>
                </div>

                <div class="card">
                    <div>
                        <div class="numbers">2000</div>
                        <div class="cardName">Lower Range</div>
                    </div>

                    <div class="iconBx">
                        <ion-icon name="people-outline"></ion-icon>
                    </div>
                </div>
            </div>
                @csrf
                    <div class="admin-config-upper-label">
                        <label for="name">Upper Range :</label>
                        <input type="number" placeholder='Upper Range' required />
                    </div>

                    <div class="admin-config-lower-label">
                        <label for="lowername">Lower Range :</label>
                        <input type="number" placeholder='Lower Range' required />
                    </div>
                    <button class="config-btn-range">Save</button>
                </div>
            </div>
            </div>
        </div>
    </div>
</div>
@endsection

<style>
    .admin_config-title{
    margin-left: 80px;
    margin-bottom: auto;
    display: block;
}

.admin-config-btn-col {
    display: flex;
    flex-direction: column; /* makes buttons go line by line */
    gap: 40px; /* space between buttons */
    width: 100%;
    max-width: 300px; /* optional: limit width */
    margin: 40px 60px 0px;
}

.admin-config-btn-col button {
    padding: 12px 16px;
    font-size: 16px;
    border: none;
    background: #00b4eb;
    color: white;
    border-radius: 50px;
    cursor: pointer;
    transition: 0.2s ease;
    font-weight: 500;
}

.admin-config-btn-col button:hover {
    background-color: var(--btn-primary-hover-bg);
    color: var(--btn-light-bg);
}

.admin-config-visual-panel{
    background-color: transparent;
    color: var(--text-primary);
    border-right: 1px solid var(--surface-border);
}

.admin-config-visual-inner{
    border-color: var(--surface-border);
    border-width: thin;
    padding: clamp(5rem, 4vw, 3.5rem);
    background: var(--surface-card);
    border-radius: 32px;
    margin: 20px;
}

.admin-config-user-card{
    display: flex;
    gap: 80px;
    margin-bottom: 40px;
    
}

.admin-config-user-card .card{
    background: var(--surface-primary);
    border-radius: 16px;
    padding: 20px;
    width: 150px;
    box-shadow: var(--surface-elevation-1);
    display: flex;
    justify-content: space-between;
    align-items: center;
    color: var(--text-primary);
    background-color: black;
}

.admin-config-user-card .card .numbers{
    font-size: 1.8rem;
    font-weight: 600;
    text-align: center;
}


.admin-config-right-side {
    display: flex;
    flex-direction: column; /* stack items vertically */
    align-items: flex-start;  /* align items to the start (left) */
    gap: 12px;              /* spacing between items */
    width: 100%;
    max-width: 300px;       /* optional: control width */
    
    position: absolute;     /* position relative to parent or page */
    top: 190px;              /* distance from the top */
    right: 300px;            /* distance from the right */
}


.admin-config-upper-label,
.admin-config-lower-label {
    display: flex;
    align-items: center;     /* vertically centered alignment */
    gap: 20px;               /* space between label & input */
    margin-bottom: 40px;     /* space between rows */
}

.admin-config-upper-label label,
.admin-config-lower-label label {
    width: 120px;            /* fixed width so both labels align */
    font-weight: 500;
    font-size: 17px;
}

.admin-config-upper-label input,
.admin-config-lower-label input {
    flex: 1;                 /* input fills the remaining space */
    padding: 8px 25px;
    border: 1px solid #ccc;
    border-radius: 10px;
}

/*
.admin-config-right-side label {
    font-weight: bold;
    width: 100%;
    text-align: right; 
}

.admin-config-right-side input {
    width: 100%;
    padding: 10px;
    border: 1px solid #ccc;
    border-radius: 6px;
}*/

.config-btn-range {
    padding: 10px 40px;
    background: #11760a;
    color: white;
    border: none;
    border-radius: 50px;
    cursor: pointer;
    font-weight: bold;
    margin-left: 150px;
}

.config-btn-range:hover {
    background: #15940c;
}

/* ============================
   MOBILE RESPONSIVE SECTION
   ============================ */
@media (max-width: 1120px){
    .admin_config-title{
        margin-left: auto;
    }

    .admin-config-btn-col{
        margin: 40px 30px 0px;
        width: 100%;
        max-width: 200px;
    }

    .admin-config-btn-col button{
        padding: 12px 16px;
    }

    .admin-config-right-side{
        max-width: 200px;
    }

}

@media (max-width: 915px){
    .admin-config-upper-label label,
    .admin-config-lower-label label{
        width: 100px;
        font-size: 15px;
    }

    .admin-config-upper-label input,
    .admin-config-lower-label input{
        padding: 8px 16px;
    }

    .admin-config-right-side{
        max-width: 130px;
    }
}

@media (max-width: 810px){
    .admin_config-title{
        margin-left: -45px;
    }

    .admin-config-btn-col{
        margin: 40px -30px 0px;
        width: 100%;
        max-width: 200px;
    }
}
/*
@media (max-width: 992px) {
    .admin-config-btn-col {
        margin: 20px auto;
        align-items: center;
        max-width: 250px;
    }

    .admin-config-right-side {
        right: 50px; 
        top: 250px;
    }
}
*/
@media (max-width: 768px) {

    .admin_config-title{
        text-align: center;
        margin-left: 0px;
    }

    .admin-config-visual-inner {
        padding: 2rem;
        margin: 10px;
    }

    .admin-config-btn-col {
        margin: 20px auto;
        width: 100%;
        align-items: center;
    }

    .admin-config-btn-col button {
        width: 100%;
        max-width: 260px;
    }
    
    /* Make RIGHT SIDE inputs centered and full width */
    .admin-config-right-side {
        position: static;        /* remove absolute for mobile */
        margin: 40px auto 0;
        width: 100%;
        max-width: 350px;
        align-items: center; 
    }

    .admin-config-upper-label,
    .admin-config-lower-label {
        flex-direction: column;  /* stack label + input */
        align-items: flex-start;
        gap: 10px;
        margin-bottom: 25px;
        width: 100%;
    }

    .admin-config-upper-label label,
    .admin-config-lower-label label {
        width: 100%;
        font-size: 16px;
    }

    .admin-config-upper-label input,
    .admin-config-lower-label input {
        width: 100%;
    }

    .config-btn-range {
        margin-left: 0;
        width: 100%;
        padding: 12px;
        max-width: 200px;
        text-align: center;
    }
}

@media (max-width: 480px) {

    .admin_config-title {
        margin-left: 0;
        text-align: center;
    }

    .admin-config-btn-col button {
        padding: 12px;
        font-size: 15px;
    }

    .admin-config-right-side {
        max-width: 300px;
    }

    .config-btn-range {
        max-width: 180px;
        padding: 10px 20px;
    }
}


    
</style>
